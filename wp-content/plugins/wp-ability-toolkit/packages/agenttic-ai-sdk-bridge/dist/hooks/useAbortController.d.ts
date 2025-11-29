/**
 * Hook for managing abort controller and request timeout
 */
export declare function useAbortController(): {
    createAbortController: (timeoutMs: number) => () => void;
    abort: () => void;
    getSignal: () => AbortSignal | undefined;
};
//# sourceMappingURL=useAbortController.d.ts.map