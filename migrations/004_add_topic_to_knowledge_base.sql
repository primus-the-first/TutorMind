-- Migration 004: Add topic column to knowledge_base
-- Allows tagging chunks by subject area (e.g. 'learning_strategies', 'general')
-- and enables boosted retrieval scoring for specific topics.

ALTER TABLE knowledge_base
    ADD COLUMN topic VARCHAR(100) NOT NULL DEFAULT 'general' AFTER source_type,
    ADD INDEX idx_topic (topic);
