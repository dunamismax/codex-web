import { boolean, pgTable, text, timestamp, uuid } from "drizzle-orm/pg-core";

export const users = pgTable("users", {
  id: uuid("id").defaultRandom().primaryKey(),
  email: text("email").notNull().unique(),
  displayName: text("display_name"),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
  updatedAt: timestamp("updated_at", { withTimezone: true }).defaultNow().notNull(),
});

export const codexSessions = pgTable("codex_sessions", {
  id: uuid("id").defaultRandom().primaryKey(),
  threadId: text("thread_id").notNull().unique(),
  workspaceRoot: text("workspace_root").notNull(),
  cwd: text("cwd").notNull(),
  model: text("model").notNull(),
  reasoningEffort: text("reasoning_effort").notNull(),
  fullAuto: boolean("full_auto").notNull().default(false),
  sandboxMode: text("sandbox_mode").notNull(),
  approvalPolicy: text("approval_policy").notNull(),
  webSearch: boolean("web_search").notNull().default(false),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
  updatedAt: timestamp("updated_at", { withTimezone: true }).defaultNow().notNull(),
});
