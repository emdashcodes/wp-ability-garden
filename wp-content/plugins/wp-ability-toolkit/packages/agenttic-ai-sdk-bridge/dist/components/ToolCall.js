/**
 * Tool Call Display Component
 * Shows tool execution status and results in the chat UI
 */
import React from 'react';
import { useState } from '@wordpress/element';
import { Spinner, Icon, Tooltip, Button } from '@wordpress/components';
import { check, closeSmall, update, chevronUp, chevronDown, copySmall, } from '@wordpress/icons';
import ReactMarkdown from 'react-markdown';
import { copyToClipboard } from '../utils/copyToClipboard.js';
export function ToolCall({ toolCall }) {
    const [isOpen, setIsOpen] = useState(false);
    const [copiedInput, setCopiedInput] = useState(false);
    const [copiedOutput, setCopiedOutput] = useState(false);
    const [copiedInfo, setCopiedInfo] = useState(false);
    const [copiedThought, setCopiedThought] = useState(false);
    // Special rendering for certain Abilities
    const isThinkAbility = toolCall.name === 'wp-ability-toolkit/think';
    const isNavigateAbility = toolCall.name === 'wp-ability-toolkit/navigate';
    const isReloadAbility = toolCall.name === 'wp-ability-toolkit/reload';
    if (isThinkAbility) {
        // Extract thought from output (complete) or input (streaming)
        const thought = toolCall.output &&
            typeof toolCall.output === 'object' &&
            'thought' in toolCall.output
            ? toolCall.output.thought
            : toolCall.input &&
                typeof toolCall.input === 'object' &&
                'thought' in toolCall.input
                ? toolCall.input.thought
                : typeof toolCall.output === 'string'
                    ? toolCall.output
                    : null;
        const isStreaming = toolCall.status === 'pending';
        const isComplete = toolCall.status === 'success';
        return (React.createElement("div", { style: {
                marginTop: '2px',
                marginBottom: '4px',
                fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
            } },
            React.createElement("button", { onClick: () => setIsOpen(!isOpen), style: {
                    display: 'flex',
                    alignItems: 'center',
                    gap: '8px',
                    width: '100%',
                    padding: '0',
                    background: 'none',
                    border: 'none',
                    cursor: 'pointer',
                    fontSize: '13px',
                    color: '#757575',
                    transition: 'color 0.15s ease',
                    outline: 'none',
                }, onMouseEnter: (e) => {
                    e.currentTarget.style.color = '#1e1e1e';
                }, onMouseLeave: (e) => {
                    e.currentTarget.style.color = '#757575';
                } },
                React.createElement("span", null, isStreaming
                    ? 'Thinking...'
                    : isComplete
                        ? 'Thought for a moment'
                        : 'Thinking...'),
                React.createElement(Icon, { icon: isOpen ? chevronUp : chevronDown, size: 16, style: {
                        transition: 'transform 0.2s ease',
                        color: 'inherit',
                    } })),
            isOpen && thought && (React.createElement("div", { style: {
                    marginTop: '6px',
                    position: 'relative',
                } },
                React.createElement("div", { style: {
                        padding: '12px 16px',
                        backgroundColor: '#f6f7f7',
                        borderLeft: `3px solid ${isStreaming ? '#2271b1' : '#8c8f94'}`,
                        borderRadius: '4px',
                        fontSize: '13px',
                        lineHeight: '1.6',
                        color: '#50575e',
                        animation: 'slideIn 0.2s ease',
                    } },
                    React.createElement(ReactMarkdown, null, thought),
                    isStreaming && (React.createElement("span", { className: "streaming-cursor", style: {
                            display: 'inline-block',
                            width: '2px',
                            height: '1em',
                            backgroundColor: '#2271b1',
                            marginLeft: '2px',
                            verticalAlign: 'text-bottom',
                            animation: 'blink 1s step-end infinite',
                        } }))),
                React.createElement(Tooltip, { text: copiedThought ? 'Copied!' : 'Copy thought' },
                    React.createElement(Button, { icon: copiedThought ? check : copySmall, size: "small", variant: "secondary", onClick: (e) => {
                            e.stopPropagation();
                            copyToClipboard(thought, setCopiedThought);
                        }, style: {
                            position: 'absolute',
                            top: '8px',
                            right: '8px',
                            minWidth: 'auto',
                            height: '24px',
                            padding: '0 8px',
                        } })))),
            React.createElement("style", null, `
					@keyframes slideIn {
						from {
							opacity: 0;
							transform: translateY(-4px);
						}
						to {
							opacity: 1;
							transform: translateY(0);
						}
					}
					@keyframes blink {
						0%, 50% {
							opacity: 1;
						}
						51%, 100% {
							opacity: 0;
						}
					}
				`)));
    }
    // Special rendering for navigate/reload abilities
    if (isNavigateAbility || isReloadAbility) {
        const label = toolCall.output &&
            typeof toolCall.output === 'object' &&
            'label' in toolCall.output
            ? toolCall.output.label
            : null;
        const isComplete = toolCall.status === 'success';
        // Only show if complete
        if (!isComplete) {
            return null;
        }
        // Determine text to display
        let displayText;
        if (label) {
            const prefix = isNavigateAbility ? 'Navigated to' : 'Reloaded';
            displayText = `${prefix} ${label}`;
        }
        else {
            displayText = isNavigateAbility ? 'Navigated' : 'Reloaded';
        }
        return (React.createElement("div", { style: {
                marginTop: '8px',
                marginBottom: '8px',
                padding: '0',
                fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
                fontSize: '13px',
                color: '#757575',
                textAlign: 'center',
                borderTop: '1px solid #dcdcde',
                borderBottom: '1px solid #dcdcde',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                gap: '8px',
            } },
            React.createElement("span", { style: {
                    padding: '6px 0',
                    fontStyle: 'italic',
                } }, displayText)));
    }
    const getStatusIcon = () => {
        switch (toolCall.status) {
            case 'pending':
                return (React.createElement(Spinner, { style: { margin: 0, width: '16px', height: '16px' } }));
            case 'success':
                return React.createElement(Icon, { icon: check, size: 16 });
            case 'error':
                return React.createElement(Icon, { icon: closeSmall, size: 16 });
            default:
                return React.createElement(Icon, { icon: update, size: 16 });
        }
    };
    const getStatusClass = () => {
        switch (toolCall.status) {
            case 'pending':
                return 'tool-call-pending';
            case 'success':
                return 'tool-call-success';
            case 'error':
                return 'tool-call-error';
            default:
                return 'tool-call-default';
        }
    };
    const getStatusText = () => {
        switch (toolCall.status) {
            case 'pending':
                return 'Executing...';
            case 'success':
                return 'Completed';
            case 'error':
                return 'Failed';
            default:
                return 'Unknown';
        }
    };
    const getDisplayName = () => {
        // Extract ability name from namespace/ability-name format
        // e.g., "my-plugin/get-posts" -> "Get Posts"
        const parts = toolCall.name.split('/');
        if (parts.length === 2) {
            const abilityName = parts[1];
            // Convert kebab-case to Title Case
            return abilityName
                .split('-')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ');
        }
        return toolCall.name;
    };
    const getPendingText = () => {
        const displayName = getDisplayName();
        return `Using ${displayName}...`;
    };
    // Pending state shows in a yellow box
    if (toolCall.status === 'pending') {
        return (React.createElement("div", { className: "tool-call-wrapper tool-call-pending", style: {
                marginTop: '2px',
                marginBottom: '2px',
                border: '1px solid #dcdcde',
                borderRadius: '4px',
                overflow: 'hidden',
                width: '100%',
                maxWidth: '100%',
                minWidth: 0,
                boxSizing: 'border-box',
            } },
            React.createElement("div", { className: "tool-call-header", style: {
                    display: 'flex',
                    alignItems: 'center',
                    gap: '8px',
                    padding: '8px 12px',
                    fontSize: '11px',
                    minWidth: 0,
                    width: '100%',
                    maxWidth: '100%',
                    boxSizing: 'border-box',
                    overflow: 'hidden',
                } },
                React.createElement("span", { className: "pending-spinner", style: {
                        display: 'inline-block',
                        width: '16px',
                        height: '16px',
                        border: '2px solid #f0f0f1',
                        borderTopColor: '#dba617',
                        borderRadius: '50%',
                        animation: 'spin 0.8s linear infinite',
                        flexShrink: 0,
                    } }),
                React.createElement("span", { style: {
                        fontWeight: 600,
                        flex: 1,
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                        whiteSpace: 'nowrap',
                        minWidth: 0,
                    } }, getDisplayName())),
            React.createElement("style", null, `
					@keyframes spin {
						to {
							transform: rotate(360deg);
						}
					}
				`)));
    }
    return (React.createElement("div", { className: `tool-call-wrapper ${getStatusClass()}`, style: {
            marginTop: '2px',
            marginBottom: '2px',
            border: '1px solid #dcdcde',
            borderRadius: '4px',
            overflow: 'hidden',
            width: '100%',
            maxWidth: '100%',
            minWidth: 0,
            boxSizing: 'border-box',
        } },
        React.createElement("div", { className: "tool-call-header", onClick: () => setIsOpen(!isOpen), style: {
                display: 'flex',
                alignItems: 'center',
                gap: '8px',
                padding: '8px 12px',
                cursor: 'pointer',
                userSelect: 'none',
                fontSize: '11px',
                transition: 'background-color 0.1s ease',
                minWidth: 0,
                width: '100%',
                maxWidth: '100%',
                boxSizing: 'border-box',
                overflow: 'hidden',
            } },
            React.createElement(Tooltip, { text: getStatusText() },
                React.createElement("span", { style: {
                        display: 'flex',
                        alignItems: 'center',
                        flexShrink: 0,
                    } }, getStatusIcon())),
            React.createElement(Tooltip, { text: toolCall.name },
                React.createElement("span", { style: {
                        fontWeight: 600,
                        flex: 1,
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                        whiteSpace: 'nowrap',
                        minWidth: 0,
                    } }, getDisplayName())),
            React.createElement(Icon, { icon: isOpen ? chevronUp : chevronDown, size: 18, style: {
                    color: '#757575',
                    flexShrink: 0,
                } })),
        isOpen && (React.createElement("div", { className: "tool-call-body", style: {
                padding: '12px',
                borderTop: '1px solid #f0f0f1',
                overflow: 'hidden',
                minWidth: 0,
                width: '100%',
                maxWidth: '100%',
                boxSizing: 'border-box',
            } },
            React.createElement("div", { style: { marginBottom: '12px', minWidth: 0 } },
                React.createElement("div", { style: {
                        fontWeight: 600,
                        fontSize: '11px',
                        color: '#757575',
                        marginBottom: '6px',
                        textTransform: 'uppercase',
                        letterSpacing: '0.5px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                    } },
                    React.createElement("span", null, "Ability Info"),
                    React.createElement(Tooltip, { text: copiedInfo ? 'Copied!' : 'Copy ability info' },
                        React.createElement(Button, { icon: copiedInfo ? check : copySmall, size: "small", variant: "secondary", onClick: (e) => {
                                e.stopPropagation();
                                const infoText = `${toolCall.name}\nID: ${toolCall.id}`;
                                copyToClipboard(infoText, setCopiedInfo);
                            }, style: {
                                minWidth: 'auto',
                                height: '24px',
                                padding: '0 8px',
                            } }))),
                React.createElement("pre", { style: {
                        backgroundColor: '#f6f7f7',
                        border: '1px solid #dcdcde',
                        padding: '12px',
                        borderRadius: '4px',
                        overflow: 'auto',
                        margin: 0,
                        fontSize: '12px',
                        fontFamily: 'Consolas, Monaco, monospace',
                        maxHeight: '200px',
                        width: '100%',
                        lineHeight: '1.5',
                        whiteSpace: 'pre',
                        boxSizing: 'border-box',
                    } },
                    toolCall.name,
                    '\n',
                    "ID: ",
                    toolCall.id)),
            toolCall.input && (React.createElement("div", { style: { marginBottom: '12px', minWidth: 0 } },
                React.createElement("div", { style: {
                        fontWeight: 600,
                        fontSize: '11px',
                        color: '#757575',
                        marginBottom: '6px',
                        textTransform: 'uppercase',
                        letterSpacing: '0.5px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                    } },
                    React.createElement("span", null, "Input"),
                    React.createElement(Tooltip, { text: copiedInput ? 'Copied!' : 'Copy input' },
                        React.createElement(Button, { icon: copiedInput ? check : copySmall, size: "small", variant: "secondary", onClick: (e) => {
                                e.stopPropagation();
                                copyToClipboard(JSON.stringify(toolCall.input, null, 2), setCopiedInput);
                            }, style: {
                                minWidth: 'auto',
                                height: '24px',
                                padding: '0 8px',
                            } }))),
                React.createElement("pre", { style: {
                        backgroundColor: '#f6f7f7',
                        border: '1px solid #dcdcde',
                        padding: '12px',
                        borderRadius: '4px',
                        overflow: 'auto',
                        margin: 0,
                        fontSize: '12px',
                        fontFamily: 'Consolas, Monaco, monospace',
                        maxHeight: '200px',
                        width: '100%',
                        lineHeight: '1.5',
                        whiteSpace: 'pre',
                        boxSizing: 'border-box',
                    } }, JSON.stringify(toolCall.input, null, 2)))),
            toolCall.status === 'success' && toolCall.output && (React.createElement("div", { style: { marginBottom: '12px', minWidth: 0 } },
                React.createElement("div", { style: {
                        fontWeight: 600,
                        fontSize: '11px',
                        color: '#757575',
                        marginBottom: '6px',
                        textTransform: 'uppercase',
                        letterSpacing: '0.5px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                    } },
                    React.createElement("span", null, "Output"),
                    React.createElement(Tooltip, { text: copiedOutput ? 'Copied!' : 'Copy output' },
                        React.createElement(Button, { icon: copiedOutput ? check : copySmall, size: "small", variant: "secondary", onClick: (e) => {
                                e.stopPropagation();
                                const outputText = typeof toolCall.output ===
                                    'string'
                                    ? toolCall.output
                                    : JSON.stringify(toolCall.output, null, 2);
                                copyToClipboard(outputText, setCopiedOutput);
                            }, style: {
                                minWidth: 'auto',
                                height: '24px',
                                padding: '0 8px',
                            } }))),
                React.createElement("pre", { style: {
                        backgroundColor: '#f6f7f7',
                        border: '1px solid #dcdcde',
                        padding: '12px',
                        borderRadius: '4px',
                        overflow: 'auto',
                        margin: 0,
                        fontSize: '12px',
                        fontFamily: 'Consolas, Monaco, monospace',
                        maxHeight: '200px',
                        width: '100%',
                        lineHeight: '1.5',
                        whiteSpace: 'pre',
                        boxSizing: 'border-box',
                    } }, typeof toolCall.output === 'string'
                    ? toolCall.output
                    : JSON.stringify(toolCall.output, null, 2)))),
            toolCall.status === 'error' && toolCall.error && (React.createElement("div", { style: { marginBottom: '12px', minWidth: 0 } },
                React.createElement("div", { style: {
                        fontWeight: 600,
                        fontSize: '11px',
                        color: '#757575',
                        marginBottom: '6px',
                        textTransform: 'uppercase',
                        letterSpacing: '0.5px',
                    } }, "Error"),
                React.createElement("div", { style: {
                        backgroundColor: '#fcf0f1',
                        border: '1px solid #d63638',
                        color: '#d63638',
                        padding: '12px',
                        borderRadius: '4px',
                        fontSize: '13px',
                        lineHeight: '1.5',
                        overflow: 'auto',
                        width: '100%',
                        maxHeight: '200px',
                        whiteSpace: 'pre-wrap',
                        wordBreak: 'break-word',
                        boxSizing: 'border-box',
                    } }, toolCall.error))))),
        React.createElement("style", null, `
        .tool-call-wrapper {
          font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
          contain: layout;
        }

        .tool-call-wrapper pre {
          overflow-wrap: normal;
          word-wrap: normal;
        }

        .tool-call-pending {
          border-left: 3px solid #dba617 !important;
        }

        .tool-call-pending .tool-call-header {
          background-color: #fcf9e8;
        }

        .tool-call-success {
          border-left: 3px solid #00a32a !important;
        }

        .tool-call-success .tool-call-header {
          background-color: #f6faf6;
        }

        .tool-call-error {
          border-left: 3px solid #d63638 !important;
        }

        .tool-call-error .tool-call-header {
          background-color: #fcf0f1;
        }

        .tool-call-default {
          border-left: 3px solid #8c8f94 !important;
        }

        .tool-call-default .tool-call-header {
          background-color: #f6f7f7;
        }

        .tool-call-header:hover {
          filter: brightness(0.98);
        }

        .tool-call-header:active {
          filter: brightness(0.96);
        }
      `)));
}
//# sourceMappingURL=ToolCall.js.map