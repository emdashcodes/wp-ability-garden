<?php
/**
 * Anthropic API client
 *
 * @package WP_Ability_Toolkit
 */

namespace WP_Ability_Toolkit;

/**
 * Handles communication with Anthropic API
 */
class Anthropic_Client extends AI_Client {
	/**
	 * API URL
	 *
	 * @var string
	 */
	private $api_url = 'https://api.anthropic.com/v1/messages';

	/**
	 * Anthropic API version
	 *
	 * @var string
	 */
	private $api_version = '2023-06-01';

	/**
	 * Tool use blocks collected during streaming
	 *
	 * @var array
	 */
	private $tool_use_blocks = array();

	/**
	 * Current content block being accumulated
	 *
	 * @var array|null
	 */
	private $current_block = null;

	/**
	 * Thinking content accumulated during streaming
	 *
	 * @var string
	 */
	private $thinking_content = '';

	/**
	 * Whether we're currently in a thinking block
	 *
	 * @var bool
	 */
	private $in_thinking_block = false;

	/**
	 * Thinking block ID for current session
	 *
	 * @var string
	 */
	private $thinking_block_id = '';

	/**
	 * Buffer for incomplete streaming lines
	 *
	 * @var string
	 */
	private $stream_buffer = '';

	/**
	 * Current message ID
	 *
	 * @var string
	 */
	private $message_id = '';

	/**
	 * Stream chat completion
	 *
	 * @param string                     $model   Model name.
	 * @param array                      $messages Messages array.
	 * @param Ability_Tools_Manager|null $tools_manager Tools manager instance.
	 * @param int                        $recursion_depth Current recursion depth.
	 * @return void Streams response directly and exits.
	 */
	public function stream_chat( $model, $messages, $tools_manager = null, $recursion_depth = 0 ) {
		// Set streaming headers only on first call (not on recursive calls).
		if ( 0 === $recursion_depth ) {
			$this->set_streaming_headers();
		}

		// Check recursion depth limit.
		if ( $this->check_recursion_limit( $recursion_depth ) ) {
			exit;
		}

		// Store tools manager for callback access.
		$this->tools_manager = $tools_manager;

		// Reset tracking.
		$this->tool_use_blocks = array();
		$this->current_block = null;
		$this->stream_buffer = '';
		$this->message_id = '';
		$this->thinking_content = '';
		$this->in_thinking_block = false;
		$this->thinking_block_id = '';

		// Convert messages to Anthropic format.
		$anthropic_messages = $this->convert_messages( $messages );

		// Extract system message if present.
		$system_content = '';
		foreach ( $messages as $message ) {
			if ( 'system' === $message['role'] ) {
				$system_content = $message['content'];
				break;
			}
		}

		// Extended thinking disabled for now - requires complex message history handling.
		// TODO: Re-enable with proper thinking block preservation in conversation history.
		$supports_thinking = false;

		// Set max_tokens based on thinking support.
		// When thinking is enabled, max_tokens must be >= budget_tokens.
		$thinking_budget = 16000;
		$max_tokens = $supports_thinking ? ( $thinking_budget + 8192 ) : 8192;

		// Prepare request body.
		$body_array = array(
			'model'      => $model,
			'max_tokens' => $max_tokens,
			'messages'   => $anthropic_messages,
			'stream'     => true,
		);

		// Add system message if present.
		if ( ! empty( $system_content ) ) {
			$body_array['system'] = $system_content;
		}

		// Add thinking config for supported models.
		if ( $supports_thinking ) {
			$body_array['thinking'] = array(
				'type'          => 'enabled',
				'budget_tokens' => $thinking_budget,
			);
			error_log( "WP Ability Toolkit: Extended thinking enabled with budget_tokens=$thinking_budget, max_tokens=$max_tokens" );
		}

		// Add tools if available.
		if ( $tools_manager ) {
			$tools = $tools_manager->convert_to_anthropic_tools();
			if ( ! empty( $tools ) ) {
				$body_array['tools'] = $tools;
			}
		}

		$body = wp_json_encode( $body_array );

		// Use curl for better streaming support.
		$ch = curl_init( $this->api_url );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );

		// Build headers array.
		$headers = array(
			'x-api-key: ' . $this->api_key,
			'anthropic-version: ' . $this->api_version,
			'Content-Type: application/json',
		);

		// Add beta header for extended thinking.
		if ( $supports_thinking ) {
			$headers[] = 'anthropic-beta: interleaved-thinking-2025-05-14';
		}

		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false );
		curl_setopt( $ch, CURLOPT_WRITEFUNCTION, array( $this, 'stream_callback' ) );
		curl_setopt( $ch, CURLOPT_TIMEOUT, self::CURL_TIMEOUT );

		$result = curl_exec( $ch );

		if ( curl_errno( $ch ) ) {
			/* translators: %s: cURL error message */
			$this->send_sse_error( sprintf( __( 'Connection error: %s', 'wp-ability-toolkit' ), curl_error( $ch ) ) );
		}

		curl_close( $ch );

		// Handle tool calls if any were collected.
		$this->handle_tool_calls( $model, $messages, $recursion_depth );

		exit;
	}

	/**
	 * Convert messages from OpenAI format to Anthropic format
	 *
	 * @param array $messages Messages in OpenAI format.
	 * @return array Messages in Anthropic format.
	 */
	private function convert_messages( array $messages ): array {
		$anthropic_messages = array();
		$tool_results = array();

		foreach ( $messages as $message ) {
			$role = $message['role'];

			// Skip system messages (handled separately).
			if ( 'system' === $role ) {
				continue;
			}

			// Handle tool results.
			if ( 'tool' === $role ) {
				$tool_results[] = array(
					'type'        => 'tool_result',
					'tool_use_id' => $message['tool_call_id'],
					'content'     => $message['content'],
				);
				continue;
			}

			// If we have pending tool results, add them to a user message.
			if ( ! empty( $tool_results ) && 'user' === $role ) {
				$anthropic_messages[] = array(
					'role'    => 'user',
					'content' => $tool_results,
				);
				$tool_results = array();
			}

			// Handle assistant messages with tool calls.
			if ( 'assistant' === $role && isset( $message['tool_calls'] ) ) {
				$content = array();

				// Add text content if present.
				if ( ! empty( $message['content'] ) ) {
					$content[] = array(
						'type' => 'text',
						'text' => $message['content'],
					);
				}

				// Convert tool calls to tool_use blocks.
				foreach ( $message['tool_calls'] as $tool_call ) {
					$parsed_input = json_decode( $tool_call['function']['arguments'], true );
					// Anthropic requires input to be an object, not array. Empty {} must not become [].
					if ( empty( $parsed_input ) || ! is_array( $parsed_input ) ) {
						$parsed_input = new \stdClass();
					}
					$content[] = array(
						'type'  => 'tool_use',
						'id'    => $tool_call['id'],
						'name'  => $tool_call['function']['name'],
						'input' => $parsed_input,
					);
				}

				$anthropic_messages[] = array(
					'role'    => 'assistant',
					'content' => $content,
				);
				continue;
			}

			// Regular user or assistant message.
			$anthropic_messages[] = array(
				'role'    => $role,
				'content' => $message['content'],
			);
		}

		// Add any remaining tool results.
		if ( ! empty( $tool_results ) ) {
			$anthropic_messages[] = array(
				'role'    => 'user',
				'content' => $tool_results,
			);
		}

		return $anthropic_messages;
	}

	/**
	 * Callback for streaming response
	 *
	 * @param resource $ch Curl handle.
	 * @param string   $data Chunk of data.
	 * @return int Length of data.
	 */
	public function stream_callback( $ch, $data ) {
		$original_length = strlen( $data );

		// Check if this is a JSON error response (not SSE format).
		if ( strpos( $data, '{"type":"error"' ) === 0 || strpos( $data, '{"error"' ) === 0 ) {
			$error_data = json_decode( $data, true );
			$error_message = $error_data['error']['message'] ?? 'Unknown API error';

			// Provide user-friendly error message.
			$user_message = $error_message;
			if ( strpos( $error_message, 'api_key' ) !== false || strpos( $error_message, 'authentication' ) !== false ) {
				$user_message = __( 'Invalid API key provided. Please check your Anthropic API key in the WP Ability Toolkit settings page.', 'wp-ability-toolkit' );
			}

			$this->send_sse_error( $user_message );
			return $original_length;
		}

		// Prepend any buffered incomplete line from previous chunk.
		$data = $this->stream_buffer . $data;

		// Split into lines.
		$lines = explode( "\n", $data );

		// Check if the chunk ended with a newline.
		$ends_with_newline = substr( $data, -1 ) === "\n";

		// If chunk doesn't end with newline, buffer the incomplete line.
		if ( ! $ends_with_newline ) {
			$this->stream_buffer = array_pop( $lines );
		} else {
			$this->stream_buffer = '';
		}

		// Process complete lines only.
		foreach ( $lines as $line ) {
			// Skip empty lines and event type lines.
			if ( trim( $line ) === '' || strpos( $line, 'event:' ) === 0 ) {
				continue;
			}

			// Only process SSE data lines.
			if ( strpos( $line, 'data: ' ) !== 0 ) {
				continue;
			}

			$json_data = substr( $line, 6 );

			// Parse Anthropic event.
			$event = json_decode( $json_data, true );
			if ( ! $event || ! isset( $event['type'] ) ) {
				continue;
			}

			$this->handle_event( $event );
		}

		return $original_length;
	}

	/**
	 * Handle a streaming event from Anthropic
	 *
	 * @param array $event Event data.
	 */
	private function handle_event( array $event ): void {
		$type = $event['type'];

		switch ( $type ) {
			case 'message_start':
				$this->message_id = $event['message']['id'] ?? '';
				break;

			case 'content_block_start':
				$block = $event['content_block'];
				$index = $event['index'];
				error_log( 'WP Ability Toolkit: content_block_start type=' . $block['type'] );

				if ( 'thinking' === $block['type'] ) {
					// Start of a thinking block.
					error_log( 'WP Ability Toolkit: THINKING BLOCK STARTED!' );
					$this->in_thinking_block = true;
					$this->thinking_content = '';
					$this->thinking_block_id = 'thinking-' . $this->message_id . '-' . $index;
					$this->current_block = array(
						'index' => $index,
						'type'  => 'thinking',
					);

					// Emit thinking_start event.
					echo 'data: ' . wp_json_encode(
						array(
							'thinking_start' => array(
								'id' => $this->thinking_block_id,
							),
						)
					) . "\n\n";
					flush();
				} elseif ( 'tool_use' === $block['type'] ) {
					$this->tool_use_blocks[ $index ] = array(
						'id'    => $block['id'],
						'name'  => $block['name'],
						'input' => '',
					);
					$this->current_block = array(
						'index' => $index,
						'type'  => 'tool_use',
					);
				} elseif ( 'text' === $block['type'] ) {
					$this->current_block = array(
						'index' => $index,
						'type'  => 'text',
					);
				}
				break;

			case 'content_block_delta':
				$delta = $event['delta'];
				$index = $event['index'];

				if ( 'thinking_delta' === $delta['type'] && isset( $delta['thinking'] ) ) {
					// Handle thinking content delta.
					$this->thinking_content .= $delta['thinking'];

					// Emit thinking_delta event.
					echo 'data: ' . wp_json_encode(
						array(
							'thinking_delta' => array(
								'id'      => $this->thinking_block_id,
								'content' => $delta['thinking'],
							),
						)
					) . "\n\n";
					flush();
				} elseif ( 'text_delta' === $delta['type'] && isset( $delta['text'] ) ) {
					// Only emit text if not in tool use mode.
					if ( empty( $this->tool_use_blocks ) ) {
						$simple_chunk = array(
							'delta' => $delta['text'],
							'id'    => $this->message_id,
						);
						echo 'data: ' . wp_json_encode( $simple_chunk ) . "\n\n";
						flush();
					}
				} elseif ( 'input_json_delta' === $delta['type'] && isset( $delta['partial_json'] ) ) {
					// Accumulate tool input JSON.
					if ( isset( $this->tool_use_blocks[ $index ] ) ) {
						$this->tool_use_blocks[ $index ]['input'] .= $delta['partial_json'];

						// Emit tool_call_delta event for streaming arguments.
						$ability_name = $this->tools_manager ? $this->tools_manager->unsanitize_tool_name( $this->tool_use_blocks[ $index ]['name'] ) : $this->tool_use_blocks[ $index ]['name'];
						echo 'data: ' . wp_json_encode(
							array(
								'tool_call_delta' => array(
									'id'              => $this->tool_use_blocks[ $index ]['id'],
									'name'            => $ability_name,
									'arguments_delta' => $delta['partial_json'],
								),
							)
						) . "\n\n";
						flush();
					}
				}
				break;

			case 'content_block_stop':
				// If we were in a thinking block, emit thinking_stop.
				if ( $this->in_thinking_block && $this->current_block && 'thinking' === $this->current_block['type'] ) {
					echo 'data: ' . wp_json_encode(
						array(
							'thinking_stop' => array(
								'id' => $this->thinking_block_id,
							),
						)
					) . "\n\n";
					flush();
					$this->in_thinking_block = false;
				}
				$this->current_block = null;
				break;

			case 'message_stop':
				// Don't send done yet if we have tool calls.
				if ( empty( $this->tool_use_blocks ) ) {
					echo 'data: ' . wp_json_encode( array( 'done' => true ) ) . "\n\n";
					flush();
				}
				break;

			case 'error':
				$error_message = $event['error']['message'] ?? 'Unknown error';
				$this->send_sse_error( $error_message );
				break;
		}
	}

	/**
	 * Handle tool calls after streaming completes
	 *
	 * @param string $model    Model name.
	 * @param array  $messages Original messages.
	 * @param int    $recursion_depth Current recursion depth.
	 */
	private function handle_tool_calls( $model, $messages, $recursion_depth = 0 ) {
		// If no tool calls, we're done.
		if ( empty( $this->tool_use_blocks ) ) {
			return;
		}

		// Convert tool_use_blocks to OpenAI-like format for consistency.
		$tool_calls = array();
		foreach ( $this->tool_use_blocks as $block ) {
			$tool_calls[] = array(
				'id'       => $block['id'],
				'type'     => 'function',
				'function' => array(
					'name'      => $block['name'],
					'arguments' => $block['input'],
				),
			);
		}

		// Check if any tools are client-side.
		$has_client_tools = false;
		$client_tool_calls = array();

		foreach ( $tool_calls as $tool_call ) {
			$function_name = $tool_call['function']['name'];
			$is_client = $this->tools_manager && $this->tools_manager->is_client_tool( $function_name );
			if ( $is_client ) {
				$has_client_tools = true;
				$client_tool_calls[] = $tool_call;
			}
		}

		// If we have client tools, emit assistant message and tool call events.
		if ( $has_client_tools ) {
			// First emit the assistant message with tool_calls.
			echo 'data: ' . wp_json_encode(
				array(
					'assistant_message' => array(
						'role'       => 'assistant',
						'content'    => '',
						'tool_calls' => $tool_calls,
					),
				)
			) . "\n\n";
			flush();

			// Then emit individual client tool call events.
			foreach ( $client_tool_calls as $tool_call ) {
				$parsed_args = json_decode( $tool_call['function']['arguments'], true );
				$ability_name = $this->tools_manager->unsanitize_tool_name( $tool_call['function']['name'] );
				echo 'data: ' . wp_json_encode(
					array(
						'client_tool_call' => array(
							'id'    => $tool_call['id'],
							'name'  => $ability_name,
							'input' => $parsed_args ?? array(),
						),
					)
				) . "\n\n";
				flush();
			}

			return;
		}

		// All tools are server-side, execute them.
		// First, add the assistant message with tool calls to the conversation.
		$messages[] = array(
			'role'       => 'assistant',
			'content'    => null,
			'tool_calls' => $tool_calls,
		);

		// Execute each tool and add results.
		foreach ( $tool_calls as $tool_call ) {
			$function_name = $tool_call['function']['name'];
			$parsed_args = json_decode( $tool_call['function']['arguments'], true );
			if ( ! $parsed_args ) {
				$parsed_args = array();
			}

			// Send event that we're about to execute a server-side tool.
			$ability_name = $this->tools_manager->unsanitize_tool_name( $function_name );
			echo 'data: ' . wp_json_encode(
				array(
					'server_tool_call' => array(
						'id'     => $tool_call['id'],
						'name'   => $ability_name,
						'input'  => $parsed_args,
						'status' => 'pending',
					),
				)
			) . "\n\n";
			flush();

			// Execute the tool.
			$result = $this->tools_manager->execute_server_tool( $function_name, $parsed_args );

			// Send event that tool execution completed.
			$is_error = is_wp_error( $result );
			echo 'data: ' . wp_json_encode(
				array(
					'server_tool_call' => array(
						'id'     => $tool_call['id'],
						'name'   => $ability_name,
						'input'  => $parsed_args,
						'status' => $is_error ? 'error' : 'success',
						'output' => $is_error ? null : $result,
						'error'  => $is_error ? $result->get_error_message() : null,
					),
				)
			) . "\n\n";
			flush();

			// Add tool result message.
			$messages[] = array(
				'role'         => 'tool',
				'tool_call_id' => $tool_call['id'],
				'content'      => $this->tools_manager->format_tool_result( $result ),
			);
		}

		// Make recursive call to continue conversation with incremented depth.
		$this->stream_chat( $model, $messages, $this->tools_manager, $recursion_depth + 1 );
	}
}
