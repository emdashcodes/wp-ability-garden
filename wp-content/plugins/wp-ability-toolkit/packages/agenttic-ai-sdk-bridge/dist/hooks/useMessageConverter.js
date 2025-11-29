/**
 * Hook for converting between UI and WordPress message formats
 */
export function useMessageConverter() {
    /**
     * Convert UI messages to WordPress REST API format
     */
    const toWordPressMessages = (uiMessages) => {
        return uiMessages
            .filter((msg) => msg.role === 'user' || msg.role === 'agent')
            .map((msg) => ({
            role: msg.role === 'agent' ? 'assistant' : 'user',
            content: msg.content
                .map((c) => {
                if (c.type === 'text')
                    return c.text;
                return '';
            })
                .join('')
                .trim() || '',
        }));
    };
    return {
        toWordPressMessages,
    };
}
//# sourceMappingURL=useMessageConverter.js.map