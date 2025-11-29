/**
 * Delta accumulator for smoother streaming updates
 * Based on agenttic-client's DeltaAccumulator implementation
 *
 * Uses requestAnimationFrame to batch updates and reduce re-renders
 */
/**
 * Accumulates streaming deltas with smooth UI updates
 */
export class DeltaAccumulator {
    constructor(options) {
        this.buffer = '';
        this.pendingUpdate = null;
        this.onUpdate = options.onUpdate;
        this.usePacing = options.usePacing !== false;
    }
    /**
     * Add a chunk to the accumulator
     */
    addChunk(chunk) {
        // Extract content from chunk
        // Handle both:
        // - delta as string: {"delta": "text"}
        // - delta as object: {"delta": {"content": "text"}}
        // - content as string: {"content": "text"}
        let content = '';
        if (typeof chunk.delta === 'string') {
            content = chunk.delta;
        }
        else if (chunk.delta &&
            typeof chunk.delta === 'object' &&
            'content' in chunk.delta) {
            content = chunk.delta.content || '';
        }
        else if (chunk.content) {
            content = chunk.content;
        }
        if (!content) {
            return;
        }
        // Add to buffer
        this.buffer += content;
        // Schedule update
        this.scheduleUpdate();
    }
    /**
     * Get current accumulated content
     */
    getContent() {
        return this.buffer;
    }
    /**
     * Clear the accumulator
     */
    clear() {
        this.buffer = '';
        if (this.pendingUpdate !== null) {
            cancelAnimationFrame(this.pendingUpdate);
            this.pendingUpdate = null;
        }
    }
    /**
     * Flush any pending updates immediately
     */
    flush() {
        if (this.pendingUpdate !== null) {
            cancelAnimationFrame(this.pendingUpdate);
            this.pendingUpdate = null;
        }
        if (this.buffer) {
            this.onUpdate(this.buffer);
        }
    }
    /**
     * Schedule an update using requestAnimationFrame for smooth rendering
     */
    scheduleUpdate() {
        // If pacing is disabled, update immediately
        if (!this.usePacing) {
            this.onUpdate(this.buffer);
            return;
        }
        // If update already scheduled, don't schedule another
        if (this.pendingUpdate !== null) {
            return;
        }
        // Schedule update on next animation frame
        this.pendingUpdate = requestAnimationFrame(() => {
            this.pendingUpdate = null;
            this.onUpdate(this.buffer);
        });
    }
}
/**
 * Helper function to create a delta accumulator with state setter
 */
export function createDeltaAccumulator(setContent, usePacing = true) {
    return new DeltaAccumulator({
        onUpdate: setContent,
        usePacing,
    });
}
//# sourceMappingURL=deltaAccumulator.js.map