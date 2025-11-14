-- Migration: Create websites table
-- Description: Initial table for storing user websites
-- Created: 2025-11-14

CREATE TABLE IF NOT EXISTS websites (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL CHECK (type IN ('portfolio', 'business', 'ecommerce', 'blog')),
    user_id UUID,
    status VARCHAR(50) NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'published', 'archived', 'deleted')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for better query performance
CREATE INDEX IF NOT EXISTS idx_websites_user_id ON websites(user_id);
CREATE INDEX IF NOT EXISTS idx_websites_status ON websites(status);
CREATE INDEX IF NOT EXISTS idx_websites_type ON websites(type);
CREATE INDEX IF NOT EXISTS idx_websites_updated_at ON websites(updated_at DESC);

-- Add comments for documentation
COMMENT ON TABLE websites IS 'Stores user-created websites';
COMMENT ON COLUMN websites.id IS 'Unique identifier for the website';
COMMENT ON COLUMN websites.name IS 'Display name of the website';
COMMENT ON COLUMN websites.type IS 'Type of website template (portfolio, business, ecommerce, blog)';
COMMENT ON COLUMN websites.user_id IS 'Foreign key to users table (owner of the website)';
COMMENT ON COLUMN websites.status IS 'Current status of the website';
COMMENT ON COLUMN websites.created_at IS 'Timestamp when website was created';
COMMENT ON COLUMN websites.updated_at IS 'Timestamp when website was last updated';
