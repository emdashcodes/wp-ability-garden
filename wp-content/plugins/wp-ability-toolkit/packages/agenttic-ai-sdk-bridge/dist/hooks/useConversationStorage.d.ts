/**
 * Hook for managing conversation persistence in localStorage
 */
import type { UIMessage } from '../types.js';
export declare function useConversationStorage(storageKey?: string): {
    messages: UIMessage[];
    setMessages: import("react").Dispatch<import("react").SetStateAction<UIMessage[]>>;
    isProcessing: boolean;
    setIsProcessing: import("react").Dispatch<import("react").SetStateAction<boolean>>;
};
//# sourceMappingURL=useConversationStorage.d.ts.map