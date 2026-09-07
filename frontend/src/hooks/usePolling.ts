import { useEffect, useRef, useCallback } from 'react';

interface UsePollingOptions {
  enabled?: boolean;
  interval?: number; // milliseconds
  immediate?: boolean;
}

export function usePolling(
  callback: () => void | Promise<void>,
  options: UsePollingOptions = {}
) {
  const {
    enabled = true,
    interval = 30000, // 30 seconds default
    immediate = true,
  } = options;

  const timeoutRef = useRef<NodeJS.Timeout | null>(null);
  const callbackRef = useRef(callback);

  // Update callback ref when callback changes
  useEffect(() => {
    callbackRef.current = callback;
  }, [callback]);

  const clear = useCallback(() => {
    if (timeoutRef.current) {
      clearTimeout(timeoutRef.current);
      timeoutRef.current = null;
    }
  }, []);

  useEffect(() => {
    if (!enabled) {
      clear();
      return;
    }

    const poll = async () => {
      try {
        await callbackRef.current();
      } catch (error) {
        console.error('Polling error:', error);
      }
      
      if (enabled) {
        timeoutRef.current = setTimeout(poll, interval);
      }
    };

    if (immediate) {
      poll();
    } else {
      timeoutRef.current = setTimeout(poll, interval);
    }

    return () => {
      clear();
    };
  }, [enabled, interval, immediate, clear]);

  return { clear };
}
