/**
 * Conversation persistence using localStorage
 * Based on agenttic-client's conversation storage implementation
 */
import { createElement } from '@wordpress/element';
import { ToolCall } from './components/ToolCall.js';
/**
 * Convert UIMessage to SerializableMessage for storage
 */
function messageToSerializable(message) {
    return {
        id: message.id,
        role: message.role,
        content: message.content.map((contentItem) => {
            if (contentItem.type === 'component' &&
                contentItem.componentProps?.toolCall) {
                // Store tool call data instead of component
                return {
                    type: 'component',
                    componentType: 'toolCall',
                    componentData: contentItem.componentProps.toolCall,
                };
            }
            // For text and image_url, just copy as-is (without component/componentProps)
            return {
                type: contentItem.type,
                text: contentItem.text,
                image_url: contentItem.image_url,
            };
        }),
        timestamp: message.timestamp,
        archived: message.archived,
        showIcon: message.showIcon,
        icon: message.icon,
        disabled: message.disabled,
    };
}
/**
 * Save conversation to localStorage
 */
export function saveConversation(key, messages) {
    if (!key) {
        return;
    }
    try {
        // Convert messages to serializable format
        const serializableMessages = messages.map(messageToSerializable);
        const data = {
            messages: serializableMessages,
            timestamp: Date.now(),
        };
        localStorage.setItem(key, JSON.stringify(data));
    }
    catch (_error) {
        // Handle quota exceeded or other localStorage errors
        console.warn('Failed to save conversation to localStorage:', _error);
        // Optionally clear old data if quota exceeded
        if (_error instanceof Error && _error.name === 'QuotaExceededError') {
            console.warn('localStorage quota exceeded. Consider clearing old conversations.');
        }
    }
}
/**
 * Convert SerializableMessage back to UIMessage, reconstructing components
 */
function serializableToMessage(serializableMsg) {
    return {
        id: serializableMsg.id,
        role: serializableMsg.role,
        content: serializableMsg.content.map((contentItem) => {
            if (contentItem.type === 'component' &&
                contentItem.componentType === 'toolCall' &&
                contentItem.componentData) {
                // Reconstruct the ToolCall component
                const toolCallData = contentItem.componentData;
                const ToolCallWrapper = (props) => createElement(ToolCall, { toolCall: props.toolCall });
                return {
                    type: 'component',
                    component: ToolCallWrapper,
                    componentProps: { toolCall: toolCallData },
                };
            }
            // For text and image_url, just return as-is
            return {
                type: contentItem.type,
                text: contentItem.text,
                image_url: contentItem.image_url,
            };
        }),
        timestamp: serializableMsg.timestamp,
        archived: serializableMsg.archived,
        showIcon: serializableMsg.showIcon,
        icon: serializableMsg.icon,
        disabled: serializableMsg.disabled,
    };
}
/**
 * Load conversation from localStorage
 */
export function loadConversation(key) {
    if (!key) {
        return null;
    }
    try {
        const stored = localStorage.getItem(key);
        if (!stored) {
            return null;
        }
        const data = JSON.parse(stored);
        // Validate the data structure
        if (!data.messages || !Array.isArray(data.messages)) {
            console.warn('Invalid conversation data in localStorage');
            return null;
        }
        // Convert serializable messages back to UI messages
        const uiMessages = data.messages.map(serializableToMessage);
        return uiMessages;
    }
    catch (error) {
        console.warn('Failed to load conversation from localStorage:', error);
        return null;
    }
}
/**
 * Clear conversation from localStorage
 */
export function clearConversation(key) {
    if (!key) {
        return;
    }
    try {
        localStorage.removeItem(key);
    }
    catch (error) {
        console.warn('Failed to clear conversation from localStorage:', error);
    }
}
/**
 * Check if a conversation exists in localStorage
 */
export function hasConversation(key) {
    if (!key) {
        return false;
    }
    try {
        return localStorage.getItem(key) !== null;
    }
    catch (error) {
        console.warn('Failed to check conversation in localStorage:', error);
        return false;
    }
}
/**
 * Get conversation metadata without loading full messages
 */
export function getConversationMetadata(key) {
    if (!key) {
        return null;
    }
    try {
        const stored = localStorage.getItem(key);
        if (!stored) {
            return null;
        }
        const data = JSON.parse(stored);
        return {
            timestamp: data.timestamp,
            messageCount: data.messages?.length || 0,
        };
    }
    catch (error) {
        console.warn('Failed to get conversation metadata:', error);
        return null;
    }
}
//# sourceMappingURL=conversationStorage.js.map