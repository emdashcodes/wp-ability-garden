/**
 * Delta accumulator for smoother streaming updates
 * Based on agenttic-client's DeltaAccumulator implementation
 *
 * Uses requestAnimationFrame to batch updates and reduce re-renders
 */
import type { StreamChunk } from './streamAdapter.js';
export interface DeltaAccumulatorOptions {
    /**
     * Callback invoked when accumulated content is ready to display
     */
    onUpdate: (content: string) => void;
    /**
     * Whether to use requestAnimationFrame pacing (default: true)
     * Set to false for synchronous updates (useful for testing)
     */
    usePacing?: boolean;
}
/**
 * Accumulates streaming deltas with smooth UI updates
 */
export declare class DeltaAccumulator {
    private buffer;
    private pendingUpdate;
    private onUpdate;
    private usePacing;
    constructor(options: DeltaAccumulatorOptions);
    /**
     * Add a chunk to the accumulator
     */
    addChunk(chunk: StreamChunk): void;
    /**
     * Get current accumulated content
     */
    getContent(): string;
    /**
     * Clear the accumulator
     */
    clear(): void;
    /**
     * Flush any pending updates immediately
     */
    flush(): void;
    /**
     * Schedule an update using requestAnimationFrame for smooth rendering
     */
    private scheduleUpdate;
}
/**
 * Helper function to create a delta accumulator with state setter
 */
export declare function createDeltaAccumulator(setContent: (content: string) => void, usePacing?: boolean): DeltaAccumulator;
//# sourceMappingURL=deltaAccumulator.d.ts.map