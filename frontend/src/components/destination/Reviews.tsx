'use client';

import { useState } from 'react';
import { motion } from 'framer-motion';
import { Star, ThumbsUp, MessageSquare, ChevronDown, ChevronUp } from 'lucide-react';

interface Review {
  id: string;
  author: string;
  avatar?: string;
  rating: number;
  date: string;
  text: string;
  helpful?: number;
  reply?: {
    author: string;
    text: string;
    date: string;
  };
}

interface ReviewsProps {
  reviews: Review[];
  averageRating?: number;
  totalReviews?: number;
  showReply?: boolean;
}

export function Reviews({ reviews, averageRating, totalReviews, showReply = true }: ReviewsProps) {
  const [expandedReviews, setExpandedReviews] = useState<Set<string>>(new Set());

  const toggleExpand = (reviewId: string) => {
    setExpandedReviews(prev => {
      const next = new Set(prev);
      if (next.has(reviewId)) {
        next.delete(reviewId);
      } else {
        next.add(reviewId);
      }
      return next;
    });
  };

  if (!reviews || reviews.length === 0) {
    return (
      <div className="text-center py-12 text-text-secondary">
        <MessageSquare className="w-12 h-12 mx-auto mb-4 opacity-50" />
        <p>No reviews yet</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <h3 className="text-xl font-semibold text-text-primary">Reviews</h3>
        {averageRating && (
          <div className="flex items-center gap-2">
            <Star className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
            <span className="font-semibold text-text-primary">{averageRating}</span>
            <span className="text-sm text-text-secondary">({totalReviews || reviews.length} reviews)</span>
          </div>
        )}
      </div>

      {/* Reviews List */}
      <div className="space-y-4">
        {reviews.map((review, index) => (
          <motion.div
            key={review.id}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: index * 0.05 }}
            className="bg-surface border border-border-light rounded-xl p-6"
            style={{
              borderRadius: 'var(--brand-border-radius, 16px)',
              boxShadow: 'var(--brand-shadow-sm, 0 2px 8px rgba(0,0,0,0.05))'
            }}
          >
            {/* Header */}
            <div className="flex items-start justify-between mb-4">
              <div className="flex items-center gap-3">
                {review.avatar ? (
                  <img
                    src={review.avatar}
                    alt={review.author}
                    className="w-10 h-10 rounded-full object-cover"
                  />
                ) : (
                  <div
                    className="w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold"
                    style={{
                      background: 'linear-gradient(to bottom right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))',
                      borderRadius: 'var(--brand-border-radius, 16px)'
                    }}
                  >
                    {review.author.charAt(0)}
                  </div>
                )}
                <div>
                  <p className="font-semibold text-text-primary">{review.author}</p>
                  <p className="text-sm text-text-secondary">{review.date}</p>
                </div>
              </div>
              <div className="flex items-center gap-1">
                {[...Array(5)].map((_, i) => (
                  <Star
                    key={i}
                    className={`w-4 h-4 ${i < review.rating ? 'fill-current' : ''}`}
                    style={{
                      color: i < review.rating ? 'var(--brand-primary, #FFD700)' : 'var(--color-border-light, rgba(255,255,255,0.1))'
                    }}
                  />
                ))}
              </div>
            </div>

            {/* Review Text */}
            <p className="text-text-secondary mb-4">
              {expandedReviews.has(review.id) || review.text.length <= 200
                ? review.text
                : `${review.text.substring(0, 200)}...`}
            </p>

            {review.text.length > 200 && (
              <button
                onClick={() => toggleExpand(review.id)}
                className="text-sm font-medium flex items-center gap-1 mb-4"
                style={{ color: 'var(--brand-primary, #FFD700)' }}
              >
                {expandedReviews.has(review.id) ? (
                  <>
                    Show less <ChevronUp className="w-4 h-4" />
                  </>
                ) : (
                  <>
                    Show more <ChevronDown className="w-4 h-4" />
                  </>
                )}
              </button>
            )}

            {/* Helpful */}
            {review.helpful !== undefined && (
              <div className="flex items-center gap-4">
                <button className="flex items-center gap-2 text-sm text-text-secondary hover:text-text-primary transition-colors">
                  <ThumbsUp className="w-4 h-4" />
                  <span>Helpful ({review.helpful})</span>
                </button>
              </div>
            )}

            {/* Reply */}
            {showReply && review.reply && (
              <div className="mt-4 pt-4 border-t border-border-light">
                <div className="bg-surface/50 rounded-lg p-4">
                  <p className="text-sm font-semibold text-text-primary mb-1">
                    {review.reply.author}
                    <span className="text-text-secondary font-normal ml-2">· {review.reply.date}</span>
                  </p>
                  <p className="text-sm text-text-secondary">{review.reply.text}</p>
                </div>
              </div>
            )}
          </motion.div>
        ))}
      </div>
    </div>
  );
}
