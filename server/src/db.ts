import { drizzle } from "drizzle-orm/node-postgres";
import pg from "pg";

import { env } from "./runtime";

let db: ReturnType<typeof drizzle> | null = null;

export function getDb() {
  if (!env.DATABASE_URL) {
    return null;
  }

  if (db) {
    return db;
  }

  const pool = new pg.Pool({ connectionString: env.DATABASE_URL });
  db = drizzle({ client: pool });
  return db;
}
