'use client';

import { useEffect, useRef } from 'react';
import { useRole } from '@/contexts/RoleContext';

interface ReverbHookOptions {
  onMessage?: (data: any) => void;
  onConnect?: () => void;
  onDisconnect?: () => void;
  onError?: (error: Event) => void;
}

export function useReverb(channelName: string, options: ReverbHookOptions = {}) {
  const { salonId, user } = useRole();
  const socketRef = useRef<WebSocket | null>(null);
  const channelRef = useRef<string | null>(null);

  useEffect(() => {
    if (!channelName || !salonId) return;

    // Construct the full channel name
    const fullChannel = channelName.replace('{id}', salonId);

    // Reverb WebSocket connection
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const host = process.env.NEXT_PUBLIC_REVERB_HOST || window.location.hostname;
    const port = process.env.NEXT_PUBLIC_REVERB_PORT || '8080';
    const appKey = process.env.NEXT_PUBLIC_REVERB_APP_KEY || 'local-key';

    const wsUrl = `${protocol}//${host}:${port}/app/${appKey}`;
    
    const socket = new WebSocket(wsUrl);
    socketRef.current = socket;

    socket.onopen = () => {
      console.log('Reverb connected');
      
      // Subscribe to the channel
      const subscribeMessage = {
        event: 'pusher:subscribe',
        data: {
          channel: fullChannel,
          auth: '', // For private channels, you'd need auth
        },
      };
      socket.send(JSON.stringify(subscribeMessage));
      
      options.onConnect?.();
    };

    socket.onmessage = (event) => {
      try {
        const message = JSON.parse(event.data);
        
        // Handle different event types
        if (message.event === 'pusher_internal:subscription_succeeded') {
          console.log('Subscribed to channel:', fullChannel);
        } else if (message.event && !message.event.startsWith('pusher')) {
          // Custom event
          const eventData = JSON.parse(message.data);
          options.onMessage?.({
            event: message.event,
            data: eventData,
          });
        }
      } catch (error) {
        console.error('Error parsing WebSocket message:', error);
      }
    };

    socket.onerror = (error) => {
      console.error('Reverb error:', error);
      options.onError?.(error);
    };

    socket.onclose = () => {
      console.log('Reverb disconnected');
      options.onDisconnect?.();
    };

    return () => {
      if (socketRef.current) {
        socketRef.current.close();
      }
    };
  }, [channelName, salonId]);

  return {
    isConnected: socketRef.current?.readyState === WebSocket.OPEN,
  };
}
