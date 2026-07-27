'use client';
import { useState, useRef, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Brain, Send, X, Minimize2, Maximize2, Sparkles } from 'lucide-react';
import { apiClient } from '@/lib/api-client';

interface Message {
  id: string;
  role: 'user' | 'assistant';
  text: string;
  type?: string;
  data?: { label: string; value: string | number }[];
  timestamp: Date;
}

const STARTER_PROMPTS = [
  'How is my business doing?',
  'Who are my top staff?',
  'Any churn risks?',
  'What is my net profit?',
];

function MessageBubble({ msg }: { msg: Message }) {
  const isUser = msg.role === 'user';

  // Convert **text** to bold in markdown-lite fashion
  const renderText = (text: string) => {
    const parts = text.split(/(\*\*.*?\*\*)/g);
    return parts.map((part, i) =>
      part.startsWith('**') && part.endsWith('**') ? (
        <strong key={i}>{part.slice(2, -2)}</strong>
      ) : (
        <span key={i}>{part}</span>
      )
    );
  };

  return (
    <motion.div
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className={`flex gap-3 ${isUser ? 'flex-row-reverse' : 'flex-row'}`}
    >
      {/* Avatar */}
      <div className={`flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-sm ${
        isUser
          ? 'bg-[#6C5CE7] text-white'
          : 'bg-gradient-to-br from-[#6C5CE7] to-[#A29BFE] text-white'
      }`}>
        {isUser ? '👤' : <Brain className="w-4 h-4" />}
      </div>

      {/* Bubble */}
      <div className={`max-w-[80%] flex flex-col gap-2 ${isUser ? 'items-end' : 'items-start'}`}>
        <div className={`px-4 py-3 rounded-2xl text-sm leading-relaxed ${
          isUser
            ? 'bg-[#6C5CE7] text-white rounded-tr-sm'
            : 'bg-card border border-border-light text-text-primary rounded-tl-sm'
        }`}>
          {renderText(msg.text)}
        </div>

        {/* Data Cards */}
        {msg.data && msg.data.length > 0 && (
          <div className="grid grid-cols-2 gap-2 w-full">
            {msg.data.map((d, i) => (
              <div key={i} className="bg-white/5 border border-white/10 rounded-xl px-3 py-2">
                <p className="text-[10px] text-text-secondary uppercase tracking-wider mb-1">{d.label}</p>
                <p className="text-sm font-bold text-text-primary">{d.value}</p>
              </div>
            ))}
          </div>
        )}

        <span className="text-[10px] text-text-secondary">
          {msg.timestamp.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
        </span>
      </div>
    </motion.div>
  );
}

interface CopilotChatProps {
  defaultOpen?: boolean;
  initialContext?: any;
  isEmbedded?: boolean;
}

export function CopilotChat({ defaultOpen = false, initialContext, isEmbedded = false }: CopilotChatProps = {}) {
  const [isOpen, setIsOpen] = useState(defaultOpen);
  const [isExpanded, setIsExpanded] = useState(false);
  const [messages, setMessages] = useState<Message[]>([
    {
      id: 'welcome',
      role: 'assistant',
      text: "Hi! I'm your **Yo Salon AI Copilot**. I can answer questions about your revenue, customers, churn risks, bookings, and staff performance. What would you like to know?",
      timestamp: new Date(),
    },
  ]);
  const [input, setInput] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  useEffect(() => {
    if (isOpen) {
      setTimeout(() => inputRef.current?.focus(), 100);
    }
  }, [isOpen]);

  const sendMessage = async (text?: string) => {
    const messageText = text || input.trim();
    if (!messageText || isLoading) return;

    const userMsg: Message = {
      id: Date.now().toString(),
      role: 'user',
      text: messageText,
      timestamp: new Date(),
    };
    setMessages(prev => [...prev, userMsg]);
    setInput('');
    setIsLoading(true);

    try {
      // Pass the context if available
      const response = await apiClient.copilotChat(messageText, initialContext);
      const assistantMsg: Message = {
        id: (Date.now() + 1).toString(),
        role: 'assistant',
        text: response.message,
        type: response.type,
        data: response.data,
        timestamp: new Date(),
      };
      setMessages(prev => [...prev, assistantMsg]);
    } catch (error) {
      const errorMsg: Message = {
        id: (Date.now() + 1).toString(),
        role: 'assistant',
        text: "I'm having trouble connecting to the backend. Please make sure the server is running.",
        timestamp: new Date(),
      };
      setMessages(prev => [...prev, errorMsg]);
    } finally {
      setIsLoading(false);
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  };

  const width = isExpanded ? 'w-[680px]' : 'w-[380px]';
  const height = isExpanded ? 'h-[600px]' : 'h-[500px]';

  return (
    <>
      {/* Floating Trigger Button */}
      <AnimatePresence>
        {!isOpen && (
          <motion.button
            initial={{ scale: 0, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
            exit={{ scale: 0, opacity: 0 }}
            onClick={() => setIsOpen(true)}
            className={`${isEmbedded ? 'relative' : 'fixed bottom-8 right-8'} z-50 w-14 h-14 bg-gradient-to-br from-[#6C5CE7] to-[#A29BFE] rounded-full shadow-2xl flex items-center justify-center hover:scale-110 transition-transform`}
          >
            <Brain className="w-6 h-6 text-white" />
            {/* Pulse ring */}
            <span className="absolute inset-0 rounded-full bg-[#6C5CE7] animate-ping opacity-20" />
          </motion.button>
        )}
      </AnimatePresence>

      {/* Chat Window */}
      <AnimatePresence>
        {isOpen && (
          <motion.div
            initial={{ opacity: 0, scale: 0.9, y: 20 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.9, y: 20 }}
            transition={{ type: 'spring', stiffness: 300, damping: 28 }}
            className={`${isEmbedded ? 'absolute bottom-0 right-0' : 'fixed bottom-8 right-8'} z-50 ${width} ${height} bg-surface border border-border-light rounded-3xl shadow-2xl flex flex-col overflow-hidden transition-all duration-300`}
          >
            {/* Header */}
            <div className="flex items-center justify-between px-5 py-4 border-b border-border-light bg-card/80 backdrop-blur-sm flex-shrink-0">
              <div className="flex items-center gap-3">
                <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-[#6C5CE7] to-[#A29BFE] flex items-center justify-center shadow-lg">
                  <Brain className="w-4 h-4 text-white" />
                </div>
                <div>
                  <p className="text-sm font-bold text-text-primary">Yo Copilot</p>
                  <p className="text-[10px] text-text-secondary flex items-center gap-1">
                    <span className="w-1.5 h-1.5 bg-emerald-400 rounded-full inline-block" />
                    Intelligence Engine Active
                  </p>
                </div>
              </div>
              <div className="flex items-center gap-1">
                <button
                  onClick={() => setIsExpanded(!isExpanded)}
                  className="w-7 h-7 rounded-lg hover:bg-white/10 flex items-center justify-center text-text-secondary hover:text-text-primary transition-colors"
                >
                  {isExpanded ? <Minimize2 className="w-4 h-4" /> : <Maximize2 className="w-4 h-4" />}
                </button>
                <button
                  onClick={() => setIsOpen(false)}
                  className="w-7 h-7 rounded-lg hover:bg-white/10 flex items-center justify-center text-text-secondary hover:text-text-primary transition-colors"
                >
                  <X className="w-4 h-4" />
                </button>
              </div>
            </div>

            {/* Messages */}
            <div className="flex-1 overflow-y-auto p-5 space-y-5">
              {messages.map(msg => (
                <MessageBubble key={msg.id} msg={msg} />
              ))}

              {isLoading && (
                <motion.div
                  initial={{ opacity: 0 }}
                  animate={{ opacity: 1 }}
                  className="flex gap-3 items-start"
                >
                  <div className="w-8 h-8 rounded-full bg-gradient-to-br from-[#6C5CE7] to-[#A29BFE] flex items-center justify-center">
                    <Brain className="w-4 h-4 text-white" />
                  </div>
                  <div className="bg-card border border-border-light rounded-2xl rounded-tl-sm px-4 py-3">
                    <div className="flex gap-1.5 items-center h-4">
                      {[0, 1, 2].map(i => (
                        <motion.div
                          key={i}
                          className="w-1.5 h-1.5 bg-[#6C5CE7] rounded-full"
                          animate={{ y: [0, -4, 0] }}
                          transition={{ duration: 0.6, repeat: Infinity, delay: i * 0.1 }}
                        />
                      ))}
                    </div>
                  </div>
                </motion.div>
              )}
              <div ref={messagesEndRef} />
            </div>

            {/* Starter Prompts (only shown when conversation is fresh) */}
            {messages.length <= 1 && (
              <div className="px-5 pb-3 flex flex-wrap gap-2">
                {STARTER_PROMPTS.map(prompt => (
                  <button
                    key={prompt}
                    onClick={() => sendMessage(prompt)}
                    className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#6C5CE7]/10 border border-[#6C5CE7]/30 text-[#A29BFE] text-xs font-medium hover:bg-[#6C5CE7]/20 transition-colors"
                  >
                    <Sparkles className="w-3 h-3" />
                    {prompt}
                  </button>
                ))}
              </div>
            )}

            {/* Input */}
            <div className="px-4 pb-4 pt-2 border-t border-border-light flex-shrink-0">
              <div className="flex items-center gap-2 bg-card border border-border-light rounded-2xl px-4 py-2.5">
                <input
                  ref={inputRef}
                  type="text"
                  value={input}
                  onChange={e => setInput(e.target.value)}
                  onKeyDown={handleKeyDown}
                  placeholder="Ask about revenue, bookings, staff..."
                  className="flex-1 bg-transparent text-sm text-text-primary placeholder:text-text-secondary focus:outline-none"
                  disabled={isLoading}
                />
                <button
                  onClick={() => sendMessage()}
                  disabled={!input.trim() || isLoading}
                  className="w-7 h-7 rounded-xl bg-[#6C5CE7] disabled:opacity-40 flex items-center justify-center hover:bg-[#5a4bd6] transition-colors"
                >
                  <Send className="w-3.5 h-3.5 text-white" />
                </button>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </>
  );
}
