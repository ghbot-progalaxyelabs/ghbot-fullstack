-- Migration: Add content and settings columns to websites table
-- Description: Add JSONB columns for storing website content and settings
-- Issue: #7 - Content Persistence
-- Created: 2025-11-14

-- Add content column to store pages, sections, and layouts
ALTER TABLE websites
  ADD COLUMN IF NOT EXISTS content JSONB DEFAULT '{}';

-- Add settings column for logos, colors, navbar configuration
ALTER TABLE websites
  ADD COLUMN IF NOT EXISTS settings JSONB DEFAULT '{}';

-- Create GIN index on content for faster JSONB queries
CREATE INDEX IF NOT EXISTS idx_websites_content ON websites USING GIN (content);

-- Create GIN index on settings for faster JSONB queries
CREATE INDEX IF NOT EXISTS idx_websites_settings ON websites USING GIN (settings);

-- Add comments for documentation
COMMENT ON COLUMN websites.content IS 'JSONB field storing website content (pages, sections, layouts)';
COMMENT ON COLUMN websites.settings IS 'JSONB field storing website settings (logo, colors, navbar)';

-- Example content structure:
-- {
--   "pages": [
--     {
--       "id": "page-1",
--       "title": "Home",
--       "slug": "/",
--       "sections": [...]
--     }
--   ]
-- }

-- Example settings structure:
-- {
--   "logo": "https://...",
--   "colors": {
--     "primary": "#007bff",
--     "secondary": "#6c757d"
--   },
--   "navbar": {
--     "position": "fixed",
--     "transparent": false
--   }
-- }
