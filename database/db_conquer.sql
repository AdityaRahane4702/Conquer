CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    color VARCHAR(20) NOT NULL,
    total_distance DOUBLE PRECISION DEFAULT 0,
    xp INTEGER DEFAULT 0,
    level INTEGER DEFAULT 1,
    is_admin BOOLEAN DEFAULT FALSE,
    is_banned BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE grids (
    id SERIAL PRIMARY KEY,
    grid_x INTEGER NOT NULL,
    grid_y INTEGER NOT NULL,
    owner_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    strength INTEGER DEFAULT 1,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(grid_x, grid_y)
);

CREATE TABLE user_movements (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    latitude DOUBLE PRECISION NOT NULL,
    longitude DOUBLE PRECISION NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Performance Indexes
CREATE INDEX idx_users_xp ON users(xp DESC);
CREATE INDEX idx_grids_owner_id ON grids(owner_id);
CREATE INDEX idx_user_movements_user_id_time ON user_movements(user_id, recorded_at DESC);