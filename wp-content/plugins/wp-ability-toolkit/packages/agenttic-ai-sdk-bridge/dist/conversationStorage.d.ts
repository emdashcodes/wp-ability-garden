/**
 * Conversation persistence using localStorage
 * Based on agenttic-client's conversation storage implementation
 */
import type { UIMessage, ToolCallContent } from './types.js';
interface SerializableMessage {
    id: string;
    role: 'user' | 'agent';
    content: Array<{
        type: 'text' | 'image_url' | 'component' | 'context';
        text?: string;
        image_url?: string;
        componentType?: 'toolCall';
        componentData?: ToolCallContent;
    }>;
    timestamp: number;
    archived: boolean;
    showIcon: boolean;
    icon?: string;
    disabled?: boolean;
}
export interface ConversationData {
    messages: SerializableMessage[];
    timestamp: number;
}
/**
 * Save conversation to localStorage
 */
export declare function saveConversation(key: string, messages: UIMessage[]): void;
/**
 * Load conversation from localStorage
 */
export declare function loadConversation(key: string): UIMessage[] | null;
/**
 * Clear conversation from localStorage
 */
export declare function clearConversation(key: string): void;
/**
 * Check if a conversation exists in localStorage
 */
export declare function hasConversation(key: string): boolean;
/**
 * Get conversation metadata without loading full messages
 */
export declare function getConversationMetadata(key: string): {
    timestamp: number;
    messageCount: number;
} | null;
export {};
//# sourceMappingURL=conversationStorage.d.ts.map