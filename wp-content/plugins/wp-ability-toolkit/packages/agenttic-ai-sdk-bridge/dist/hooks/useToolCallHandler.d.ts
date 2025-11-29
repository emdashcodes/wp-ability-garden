/**
 * Hook for handling tool calls (both client-side and server-side)
 * Unified management of tool rendering, execution, and status updates
 */
import type { UIMessage, WordPressMessage } from '../types.js';
import type { ToolCallEvent, ServerToolCallEvent, ToolCallDeltaEvent } from '../streamAdapter.js';
export declare function useToolCallHandler(setMessages: (value: UIMessage[] | ((prev: UIMessage[]) => UIMessage[])) => void): {
    handleServerToolCall: (serverToolCall: ServerToolCallEvent) => void;
    handleClientToolCall: (toolCall: ToolCallEvent) => Promise<{
        message: WordPressMessage;
        skipContinuation: boolean;
    }>;
    handleToolCallDelta: (delta: ToolCallDeltaEvent) => void;
};
//# sourceMappingURL=useToolCallHandler.d.ts.map