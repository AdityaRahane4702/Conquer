import psycopg2
import time
import random
import math

# Project Constants
GRID_SIZE = 0.0005
BASE_LAT = 18.5266
BASE_LNG = 73.8398

def get_db_connection():
    return psycopg2.connect(
        host="localhost",
        dbname="db_conquer",
        user="adityarahane",
        password="Aditya@4702"
    )

def get_grid_coords(lat, lng):
    return math.floor(lat / GRID_SIZE), math.floor(lng / GRID_SIZE)

def haversine(lat1, lon1, lat2, lon2):
    # Earth radius in kilometers
    R = 6371.0
    
    dlat = math.radians(lat2 - lat1)
    dlon = math.radians(lon2 - lon1)
    
    a = math.sin(dlat / 2)**2 + math.cos(math.radians(lat1)) * \
        math.cos(math.radians(lat2)) * math.sin(dlon / 2)**2
    c = 2 * math.atan2(math.sqrt(a), math.sqrt(1 - a))
    
    return R * c

# In-memory store for bot headings and cooldowns
bot_headings = {}
bot_last_capture = {} # Stores {bot_id: (grid_x, grid_y, timestamp)}

def move_bots():
    conn = get_db_connection()
    conn.autocommit = True 
    cur = conn.cursor()
    
    print(f"--- Bot Engine Started (Patrolling Mode) ---")
    
    while True:
        try:
            cur.execute("SELECT id, username FROM users WHERE is_bot = TRUE")
            bots = cur.fetchall()
            
            if not bots:
                print("No bots found... waiting.")
                time.sleep(10)
                continue

            for bot_id, username in bots:
                try:
                    # 1. Get latest location
                    cur.execute("""
                        SELECT latitude, longitude FROM user_movements 
                        WHERE user_id = %s 
                        ORDER BY recorded_at DESC LIMIT 1
                    """, (bot_id,))
                    loc = cur.fetchone()
                    
                    if not loc: continue
                    old_lat, old_lng = float(loc[0]), float(loc[1])
                    lat, lng = old_lat, old_lng

                    # 2. Maintain heading (Direction)
                    if bot_id not in bot_headings or random.random() < 0.05:
                        # Pick a new random angle (0 to 2*PI)
                        bot_headings[bot_id] = random.uniform(0, 2 * math.pi)

                    # 3. Movement Step
                    step_size = 0.00015 
                    angle = bot_headings[bot_id]
                    angle += random.uniform(-0.1, 0.1)
                    bot_headings[bot_id] = angle

                    lat += math.cos(angle) * step_size
                    lng += math.sin(angle) * step_size

                    # 4. Calculate Distance & Update Stats (EVERY STEP)
                    dist_km = haversine(old_lat, old_lng, lat, lng)
                    if dist_km > 0:
                        cur.execute("SELECT total_distance, xp FROM users WHERE id = %s", (bot_id,))
                        bot_data = cur.fetchone()
                        new_total_dist = float(bot_data[0]) + dist_km
                        new_xp = math.floor(new_total_dist / 0.5)
                        new_level = math.floor(new_xp / 20) + 1
                        
                        cur.execute("""
                            UPDATE users 
                            SET total_distance = %s, xp = %s, level = %s 
                            WHERE id = %s
                        """, (new_total_dist, new_xp, new_level, bot_id))

                    # 5. Save movement record
                    cur.execute("INSERT INTO user_movements (user_id, latitude, longitude) VALUES (%s, %s, %s)", (bot_id, lat, lng))

                    # 6. CAPTURE LOGIC (with cooldown)
                    grid_x, grid_y = get_grid_coords(lat, lng)

                    now = time.time()
                    if bot_id in bot_last_capture:
                        lgx, lgy, lt = bot_last_capture[bot_id]
                        if lgx == grid_x and lgy == grid_y and (now - lt < 30):
                            continue # Skip interaction, but movement and XP are already saved above

                    bot_last_capture[bot_id] = (grid_x, grid_y, now)

                    cur.execute("SELECT owner_id, strength FROM grids WHERE grid_x = %s AND grid_y = %s", (grid_x, grid_y))
                    existing_grid = cur.fetchone()
                    
                    if not existing_grid:
                        cur.execute("INSERT INTO grids (grid_x, grid_y, owner_id, strength) VALUES (%s, %s, %s, 1)", (grid_x, grid_y, bot_id))
                        print(f"[{username}] Captured NEW grid ({grid_x}, {grid_y})")
                    else:
                        owner, strength = existing_grid
                        if owner == bot_id:
                            if int(strength) < 5:
                                cur.execute("UPDATE grids SET strength = strength + 1 WHERE grid_x = %s AND grid_y = %s", (grid_x, grid_y))
                                print(f"[{username}] Reinforced ({grid_x}, {grid_y}) -> S:{strength+1}")
                        else:
                            new_strength = int(strength) - 1
                            if new_strength <= 0:
                                cur.execute("UPDATE grids SET owner_id = %s, strength = 1 WHERE grid_x = %s AND grid_y = %s", (bot_id, grid_x, grid_y))
                                cur.execute("INSERT INTO notifications (user_id, message, type) VALUES (%s, %s, 'attack')", 
                                           (owner, f"Intelligence Alert: Soldier {username} has conquered your grid at ({grid_x}, {grid_y})!"))
                                print(f"[{username}] TOOK OVER grid from User {owner}!")
                            else:
                                cur.execute("UPDATE grids SET strength = %s WHERE grid_x = %s AND grid_y = %s", (new_strength, grid_x, grid_y))
                                cur.execute("INSERT INTO notifications (user_id, message, type) VALUES (%s, %s, 'attack')", 
                                           (owner, f"⚠️ Alert: Your territory at ({grid_x}, {grid_y}) is under attack by Soldier {username}!"))
                                print(f"[{username}] Attacking ({grid_x}, {grid_y}) (Remaining S: {new_strength})")
                
                except Exception as bot_err:
                    print(f"Bot {username} Error: {bot_err}")
            
            # Wait 8-12 seconds before next step (Real human pace)
            time.sleep(random.uniform(8, 12))
            
        except Exception as loop_err:
            print(f"Main Loop Error: {loop_err}")
            time.sleep(5)

if __name__ == "__main__":
    try:
        move_bots()
    except KeyboardInterrupt:
        print("\nStopping bots...")
    except Exception as e:
        print(f"Bot Error: {e}")
