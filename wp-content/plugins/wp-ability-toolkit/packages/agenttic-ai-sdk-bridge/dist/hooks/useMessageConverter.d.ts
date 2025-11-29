/**
 * Hook for converting between UI and WordPress message formats
 */
import type { UIMessage, WordPressMessage } from '../types.js';
export declare function useMessageConverter(): {
    toWordPressMessages: (uiMessages: UIMessage[]) => WordPressMessage[];
};
//# sourceMappingURL=useMessageConverter.d.ts.map