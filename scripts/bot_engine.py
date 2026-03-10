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

def move_bots():
    conn = get_db_connection()
    conn.autocommit = True # Ensure we see fresh data from DB every query
    cur = conn.cursor()
    
    print(f"--- Bot Engine Started ---")
    
    while True:
        try:
            # 1. Fetch current bots dynamically
            cur.execute("SELECT id, username FROM users WHERE is_bot = TRUE")
            bots = cur.fetchall()
            
            if not bots:
                print("No bots found in database... waiting.")
                time.sleep(10)
                continue

            for bot_id, username in bots:
                try:
                    # 2. Get latest location
                    cur.execute("""
                        SELECT latitude, longitude FROM user_movements 
                        WHERE user_id = %s 
                        ORDER BY recorded_at DESC LIMIT 1
                    """, (bot_id,))
                    loc = cur.fetchone()
                    
                    if not loc:
                        # Should not happen as add_bot adds 1 movement
                        print(f"Bot {username} has no movement records. Skipping.")
                        continue

                    lat, lng = float(loc[0]), float(loc[1])

                    # --- 3. DYNAMIC MOVEMENT ---
                    # Check if the bot is already on a maxed-out grid
                    gx, gy = get_grid_coords(lat, lng)
                    cur.execute("SELECT strength FROM grids WHERE grid_x = %s AND grid_y = %s AND owner_id = %s", (gx, gy, bot_id))
                    s_res = cur.fetchone()
                    
                    # Force jump if strength is >= 5
                    is_maxed = s_res and int(s_res[0]) >= 5

                    # If maxed, jump significantly further (over 2-3 grids away)
                    jump_dist = 0.0012 if is_maxed else 0.00015
                    lat += random.uniform(-jump_dist, jump_dist)
                    lng += random.uniform(-jump_dist, jump_dist)
                    
                    # --- 4. BATTLE LOGIC MATCHING PHP ---
                    grid_x, grid_y = get_grid_coords(lat, lng)
                    
                    cur.execute("SELECT owner_id, strength FROM grids WHERE grid_x = %s AND grid_y = %s", (grid_x, grid_y))
                    existing_grid = cur.fetchone()
                    
                    if not existing_grid:
                        # New grid capture
                        cur.execute("INSERT INTO grids (grid_x, grid_y, owner_id, strength) VALUES (%s, %s, %s, 1)", (grid_x, grid_y, bot_id))
                        print(f"Bot {username} captured NEW grid ({grid_x}, {grid_y})")
                    else:
                        owner, str_val = existing_grid
                        strength = int(str_val)
                        
                        if owner == bot_id:
                            # Reinforce ONLY if strength is strictly below 5
                            if strength < 5:
                                if random.random() > 0.4:
                                    cur.execute("UPDATE grids SET strength = strength + 1 WHERE grid_x = %s AND grid_y = %s", (grid_x, grid_y))
                                    print(f"Bot {username} reinforced grid ({grid_x}, {grid_y}) (S: {strength+1})")
                            else:
                                # This print confirms it's moving on
                                print(f"Bot {username} bypassing maxed grid ({grid_x}, {grid_y})")
                        else:
                            # Attack
                            new_strength = strength - 1
                            if new_strength <= 0:
                                cur.execute("UPDATE grids SET owner_id = %s, strength = 1 WHERE grid_x = %s AND grid_y = %s", (bot_id, grid_x, grid_y))
                                print(f"Bot {username} TOOK OVER grid ({grid_x}, {grid_y})!")
                            else:
                                cur.execute("UPDATE grids SET strength = %s WHERE grid_x = %s AND grid_y = %s", (new_strength, grid_x, grid_y))
                                print(f"Bot {username} attacked grid ({grid_x}, {grid_y}) (Remaining S: {new_strength})")

                    # 5. Save movement
                    cur.execute("INSERT INTO user_movements (user_id, latitude, longitude) VALUES (%s, %s, %s)", (bot_id, lat, lng))
                
                except Exception as bot_err:
                    print(f"Error processing bot {username}: {bot_err}")
            
            time.sleep(random.uniform(8, 15))
            
        except Exception as loop_err:
            print(f"General Engine Loop Error: {loop_err}")
            time.sleep(5)

if __name__ == "__main__":
    try:
        move_bots()
    except KeyboardInterrupt:
        print("\nStopping bots...")
    except Exception as e:
        print(f"Bot Error: {e}")
