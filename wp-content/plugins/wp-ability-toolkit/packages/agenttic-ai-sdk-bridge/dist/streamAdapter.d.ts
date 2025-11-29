/**
 * Adapts WordPress REST API streaming responses to Agenttic UI format
 */
import type { AbilityInput, AbilityOutput } from '@wordpress/abilities';
import type { OpenAIToolCall } from './types.js';
export interface ToolCallEvent {
    id: string;
    name: string;
    input: AbilityInput;
}
export interface ServerToolCallEvent {
    id: string;
    name: string;
    input: AbilityInput;
    status: 'pending' | 'success' | 'error';
    output?: AbilityOutput;
    error?: string;
}
export interface ToolCallDeltaEvent {
    id: string;
    name: string;
    arguments_delta: string;
}
export interface StreamChunk {
    id?: string;
    role?: 'assistant';
    content?: string;
    delta?: string | {
        content?: string;
        [key: string]: unknown;
    };
    done?: boolean;
    client_tool_call?: ToolCallEvent;
    server_tool_call?: ServerToolCallEvent;
    tool_call_delta?: ToolCallDeltaEvent;
    assistant_message?: {
        role: 'assistant';
        content: string;
        tool_calls: OpenAIToolCall[];
    };
    error?: string;
}
/**
 * Parse Server-Sent Events (SSE) stream from WordPress
 */
export declare function parseSSEStream(response: Response): AsyncGenerator<StreamChunk, void, unknown>;
/**
 * Accumulate text from stream chunks
 */
export declare function accumulateStreamText(chunks: StreamChunk[]): string;
//# sourceMappingURL=streamAdapter.d.ts.map