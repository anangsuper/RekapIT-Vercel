-- Optimization: Add indexes to assets table for better performance
-- Based on analysis of bottlenecks in filtering and sorting.

CREATE INDEX idx_assets_id_cabang ON assets(id_cabang);
CREATE INDEX idx_assets_kondisi ON assets(kondisi);
CREATE INDEX idx_assets_created_at ON assets(created_at);

-- Optional: Add index for maintenance and repair tables for better joins
CREATE INDEX idx_maintenance_asset_id ON maintenance(asset_id);
CREATE INDEX idx_repairs_asset_id ON repairs(asset_id);
