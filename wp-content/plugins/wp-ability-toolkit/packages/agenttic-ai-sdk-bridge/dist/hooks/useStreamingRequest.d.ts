/**
 * Hook for making streaming requests to WordPress REST API
 * Combines fetch, error handling, and SSE stream parsing
 */
import type { StreamChunk } from '../streamAdapter.js';
import type { WordPressMessage, ClientAbility } from '../types.js';
export interface StreamingRequestConfig {
    endpoint: string;
    nonce: string;
    signal?: AbortSignal;
    enableStreaming?: boolean;
}
export interface StreamingRequestPayload {
    messages: WordPressMessage[];
    clientAbilities: ClientAbility[];
    clientContext: {
        url: string;
    };
}
export interface ChunkHandlerResult {
    clearAccumulator?: boolean;
}
export declare function useStreamingRequest(): {
    makeRequest: (config: StreamingRequestConfig, payload: StreamingRequestPayload, onUpdate: (content: string) => void, onChunk?: (chunk: StreamChunk) => void | Promise<void> | ChunkHandlerResult | Promise<ChunkHandlerResult | undefined> | undefined) => Promise<void>;
};
//# sourceMappingURL=useStreamingRequest.d.ts.map