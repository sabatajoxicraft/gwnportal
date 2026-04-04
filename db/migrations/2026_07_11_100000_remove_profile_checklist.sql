-- Migration: remove_profile_checklist
-- Removes the profile checklist feature schema objects from existing databases.
-- Safe to re-run: IF EXISTS guards prevent errors on already-clean databases.

DROP TABLE IF EXISTS `profile_checklist`;

ALTER TABLE `user_preferences`
    DROP COLUMN IF EXISTS `checklist_widget_dismissed`;
