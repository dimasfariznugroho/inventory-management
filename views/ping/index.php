<div class="hero-section">
    <div class="hero-content">
        <div class="status-indicator-wrapper">
            <span class="pulse-dot <?= $health->isAlive() ? 'dot-alive' : 'dot-dead' ?>"></span>
            <span class="status-text"><?= $health->isAlive() ? 'Database Engine Connected & Healthy' : 'Database Connection Offline' ?></span>
        </div>
        <h1 class="hero-title">Phase 0 Architectural Validation</h1>
        <p class="hero-desc">
            Skeleton arsitektur berlapis (Controller &rarr; Service &rarr; Repository Interface &rarr; MySQL Implementation &rarr; Entity) 
            berhasil diinisialisasi dan terverifikasi melakukan query nyata ke MySQL 8.
        </p>
    </div>
</div>

<div class="stats-grid">
    <div class="card stat-card">
        <div class="stat-icon icon-time">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-label">MySQL Server Time (NOW())</span>
            <span class="stat-value text-mono" id="server-time"><?= htmlspecialchars($health->getCurrentTime()) ?></span>
            <span class="stat-hint">Hasil query SQL langsung dari container MySQL</span>
        </div>
    </div>

    <div class="card stat-card">
        <div class="stat-icon icon-version">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="2" width="20" height="8" rx="2" ry="2"/>
                <rect x="2" y="14" width="20" height="8" rx="2" ry="2"/>
                <line x1="6" y1="6" x2="6.01" y2="6"/>
                <line x1="6" y1="18" x2="6.01" y2="18"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-label">Database Version (VERSION())</span>
            <span class="stat-value text-mono" id="mysql-version"><?= htmlspecialchars($health->getMysqlVersion()) ?></span>
            <span class="stat-hint">Engine database yang aktif</span>
        </div>
    </div>

    <div class="card stat-card">
        <div class="stat-icon icon-database">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <ellipse cx="12" cy="5" rx="9" ry="3"/>
                <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/>
                <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-label">Database Name (DATABASE())</span>
            <span class="stat-value text-mono" id="db-name"><?= htmlspecialchars($health->getDatabaseName()) ?></span>
            <span class="stat-hint">Schema aktif yang terhubung</span>
        </div>
    </div>

    <div class="card stat-card">
        <div class="stat-icon icon-status">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
        </div>
        <div class="stat-content">
            <span class="stat-label">Connection Status</span>
            <span class="stat-value <?= $health->isAlive() ? 'text-success' : 'text-danger' ?>" id="connection-status">
                <?= $health->isAlive() ? 'ALIVE (1)' : 'OFFLINE (0)' ?>
            </span>
            <span class="stat-hint">Injected via Database PDO class</span>
        </div>
    </div>
</div>

<div class="architecture-section">
    <div class="card architecture-card">
        <div class="card-header">
            <h2 class="card-title">Vertical Slice Architecture Flow</h2>
            <span class="badge badge-secondary">Zero Framework &bull; Pure Clean Architecture</span>
        </div>
        <div class="card-body">
            <div class="flow-steps">
                <div class="flow-step">
                    <div class="step-badge">1</div>
                    <div class="step-info">
                        <span class="step-type">Entry / Routing</span>
                        <span class="step-class">public/index.php</span>
                        <p class="step-detail">Front-controller native memetakan URL dan merangkai Dependency (Composition Root).</p>
                    </div>
                </div>

                <div class="flow-arrow">&rarr;</div>

                <div class="flow-step">
                    <div class="step-badge">2</div>
                    <div class="step-info">
                        <span class="step-type">Presentation</span>
                        <span class="step-class">PingController</span>
                        <p class="step-detail">Menerima HTTP request, memanggil service layer, dan memuat view.</p>
                    </div>
                </div>

                <div class="flow-arrow">&rarr;</div>

                <div class="flow-step">
                    <div class="step-badge">3</div>
                    <div class="step-info">
                        <span class="step-type">Business Rule</span>
                        <span class="step-class">PingService</span>
                        <p class="step-detail">Mengeksekusi aturan bisnis. Hanya bergantung pada PingRepositoryInterface.</p>
                    </div>
                </div>

                <div class="flow-arrow">&rarr;</div>

                <div class="flow-step">
                    <div class="step-badge">4</div>
                    <div class="step-info">
                        <span class="step-type">Data Access Contract</span>
                        <span class="step-class">PingRepositoryInterface</span>
                        <p class="step-detail">Interface abstrak untuk fleksibilitas implementasi MySQL nyata dan in-memory fake.</p>
                    </div>
                </div>

                <div class="flow-arrow">&rarr;</div>

                <div class="flow-step">
                    <div class="step-badge">5</div>
                    <div class="step-info">
                        <span class="step-type">Data Access Impl</span>
                        <span class="step-class">MySQLPingRepository</span>
                        <p class="step-detail">Mengakses MySQL melalui objek Database (PDO Prepared Statement), mengembalikan PingResult entity.</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="actions-group">
                <a href="/ping" class="btn btn-primary" id="btn-refresh">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/>
                    </svg>
                    Refresh Status (HTTP GET)
                </a>
                <button type="button" class="btn btn-secondary" id="btn-ajax-test">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 4 23 10 17 10"/>
                        <polyline points="1 20 1 14 7 14"/>
                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                    </svg>
                    Test Live Fetch API
                </button>
            </div>
            <div id="ajax-toast" class="ajax-toast hidden"></div>
        </div>
    </div>
</div>
