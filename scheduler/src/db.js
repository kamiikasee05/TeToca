const { DatabaseSync } = require('node:sqlite');
const path = require('path');
const fs = require('fs');

const DATA_DIR = process.env.DATA_DIR || path.join(__dirname, '..', 'data');
const DB_PATH = path.join(DATA_DIR, 'scheduler.db');

let db;

function getDb() {
  if (!db) {
    fs.mkdirSync(DATA_DIR, { recursive: true });
    db = new DatabaseSync(DB_PATH);
    db.exec('PRAGMA journal_mode = WAL');
    db.exec('PRAGMA foreign_keys = ON');
    initSchema();
  }
  return db;
}

function initSchema() {
  db.exec(`
    CREATE TABLE IF NOT EXISTS services (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      duration INTEGER NOT NULL,
      price REAL DEFAULT 0,
      currency TEXT DEFAULT 'ARS',
      description TEXT DEFAULT '',
      slot_interval INTEGER DEFAULT 15,
      attendants_number INTEGER DEFAULT 1,
      category_id INTEGER DEFAULT NULL
    )
  `);

  db.exec(`
    CREATE TABLE IF NOT EXISTS customers (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      first_name TEXT NOT NULL,
      last_name TEXT DEFAULT '',
      email TEXT DEFAULT '',
      phone TEXT NOT NULL,
      created_at TEXT DEFAULT (datetime('now'))
    )
  `);

  db.exec(`
    CREATE TABLE IF NOT EXISTS appointments (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      start TEXT NOT NULL,
      end TEXT NOT NULL,
      service_id INTEGER NOT NULL REFERENCES services(id),
      customer_id INTEGER NOT NULL REFERENCES customers(id),
      provider_id INTEGER DEFAULT 5,
      status TEXT DEFAULT 'confirmed',
      notes TEXT DEFAULT '',
      hash TEXT DEFAULT '',
      created_at TEXT DEFAULT (datetime('now'))
    )
  `);

  db.exec(`
    CREATE TABLE IF NOT EXISTS provider_settings (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      provider_id INTEGER DEFAULT 5,
      first_name TEXT DEFAULT 'Laura',
      last_name TEXT DEFAULT '',
      email TEXT DEFAULT '',
      phone TEXT DEFAULT '',
      timezone TEXT DEFAULT 'America/Argentina/Cordoba',
      working_plan TEXT DEFAULT '{}',
      username TEXT DEFAULT 'laura',
      notifications INTEGER DEFAULT 0,
      calendar_view TEXT DEFAULT 'default'
    )
  `);

  db.exec(`
    CREATE INDEX IF NOT EXISTS idx_appointments_start ON appointments(start)
  `);
  db.exec(`
    CREATE INDEX IF NOT EXISTS idx_appointments_customer ON appointments(customer_id)
  `);
  db.exec(`
    CREATE INDEX IF NOT EXISTS idx_customers_phone ON customers(phone)
  `);

  const count = db.prepare('SELECT COUNT(*) as c FROM provider_settings').get();
  if (count.c === 0) {
    const defaultPlan = {
      monday: { start: '09:00', end: '18:00', breaks: [{ start: '13:00', end: '14:00' }] },
      tuesday: { start: '09:00', end: '18:00', breaks: [{ start: '13:00', end: '14:00' }] },
      wednesday: { start: '09:00', end: '18:00', breaks: [{ start: '13:00', end: '14:00' }] },
      thursday: { start: '09:00', end: '18:00', breaks: [{ start: '13:00', end: '14:00' }] },
      friday: { start: '09:00', end: '18:00', breaks: [{ start: '13:00', end: '14:00' }] },
      saturday: { start: null, end: null, breaks: [] },
      sunday: { start: null, end: null, breaks: [] },
    };
    db.prepare(`
      INSERT INTO provider_settings (provider_id, working_plan)
      VALUES (5, ?)
    `).run(JSON.stringify(defaultPlan));
  }
}

module.exports = { getDb };
