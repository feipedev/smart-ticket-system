-- ================================================================
-- SCHEMA SQLite — Smart Ticket System
-- Executa automaticamente na primeira conexão via Database::migrate()
-- ================================================================

-- ── Usuarios ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT    NOT NULL,
    email         TEXT    NOT NULL UNIQUE COLLATE NOCASE,
    password_hash TEXT    NOT NULL,
    role          TEXT    NOT NULL DEFAULT 'user' CHECK(role IN ('user', 'admin', 'technician')),
    created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ── Tickets / Ordens de Serviço ───────────────────────────────────
CREATE TABLE IF NOT EXISTS tickets (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    title       TEXT    NOT NULL,
    description TEXT    NOT NULL,
    status      TEXT    NOT NULL DEFAULT 'open'
                        CHECK(status IN ('open','in_progress','resolved','closed')),
    priority    TEXT    NOT NULL DEFAULT 'medium'
                        CHECK(priority IN ('low','medium','high','urgent')),
    category    TEXT,
    user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    assigned_to TEXT,
    resolved_at TEXT,
    created_at  TEXT    NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ── Índices de Performance ────────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_tickets_status   ON tickets(status);
CREATE INDEX IF NOT EXISTS idx_tickets_priority ON tickets(priority);
CREATE INDEX IF NOT EXISTS idx_tickets_user_id  ON tickets(user_id);
CREATE INDEX IF NOT EXISTS idx_tickets_created  ON tickets(created_at DESC);

-- ── Trigger: atualiza updated_at automaticamente ──────────────────
CREATE TRIGGER IF NOT EXISTS trg_tickets_updated_at
AFTER UPDATE ON tickets FOR EACH ROW
BEGIN
    UPDATE tickets SET updated_at = datetime('now') WHERE id = OLD.id;
END;

-- ── Seed: Admin padrão (senha: Admin@1234) ────────────────────────
-- ALTERE A SENHA em produção. Hash gerado com password_hash(ARGON2ID)
INSERT OR IGNORE INTO users (name, email, password_hash, role)
VALUES (
    'Admin',
    'admin@ticketsystem.local',
    '$argon2id$v=19$m=65536,t=4,p=1$c2FsdHNhbHRzYWx0c2Fs$PLACEHOLDER_HASH_CHANGE_ME',
    'admin'
);

-- ── Tickets de Demonstração ───────────────────────────────────────
INSERT OR IGNORE INTO tickets (id, title, description, status, priority, user_id, category) VALUES
(1, 'Máquina Samsung não centrifuga', 'Máquina completa ciclo mas não centrifuga. Motor gira mas para ao atingir velocidade máxima.', 'open', 'high', 1, 'Centrifugação'),
(2, 'Brastemp 11kg - vazamento pela porta', 'Vedação da borracha aparentemente ok, mas perde água durante ciclo de enxágue.', 'in_progress', 'medium', 1, 'Vedação'),
(3, 'Electrolux LAC11 - painel sem resposta', 'Placa de interface não responde a nenhum botão. Verificar tensão da fonte.', 'resolved', 'urgent', 1, 'Eletrônica'),
(4, 'Consul Facilite - barulho ao centrifugar', 'Ruído metálico durante centrifugação. Possível rolamento ou cesto desbalanceado.', 'open', 'low', 1, 'Mecânica');
