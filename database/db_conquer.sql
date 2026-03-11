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
    is_bot BOOLEAN DEFAULT FALSE,
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

CREATE TABLE user_sessions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP,
    distance_km DOUBLE PRECISION DEFAULT 0,
    xp_gain INTEGER DEFAULT 0,
    grids_captured INTEGER DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active'
);

CREATE TABLE user_movements (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    latitude DOUBLE PRECISION NOT NULL,
    longitude DOUBLE PRECISION NOT NULL,
    session_id INTEGER REFERENCES user_sessions(id) ON DELETE SET NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Performance Indexes
CREATE INDEX idx_users_xp ON users(xp DESC);
CREATE INDEX idx_grids_owner_id ON grids(owner_id);
CREATE INDEX idx_user_movements_user_id_time ON user_movements(user_id, recorded_at DESC);
CREATE INDEX idx_user_sessions_user_id ON user_sessions(user_id);

CREATE TABLE notifications (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    message TEXT NOT NULL,
    type VARCHAR(50), -- 'attack', 'mission', 'level_up'
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_notifications_user_id ON notifications(user_id, created_at DESC);