</main>
</div>

<style>
.admin-layout { display: grid; grid-template-columns: 230px 1fr; min-height: calc(100vh - var(--topbar-h)); }
.admin-sidebar {
    background: var(--surface); border-right: 1px solid var(--border);
    padding-bottom: 20px;
}
.admin-nav-item {
    display: flex; align-items: center; gap: 10px;
    padding: 11px 18px; font-size: 14px; font-weight: 500;
    color: var(--text-muted); border-left: 3px solid transparent;
    transition: all .14s;
}
.admin-nav-item:hover { background: var(--surface-2); color: var(--text); }
.admin-nav-item.activo {
    background: var(--primary-light); color: var(--primary-dark);
    border-left-color: var(--primary); font-weight: 700;
}
.dark-theme .admin-nav-item.activo { color: #4ade80; }
.admin-content { padding: 26px 28px; min-width: 0; }
@media (max-width: 900px) {
    .admin-layout { grid-template-columns: 1fr; }
    .admin-sidebar { display: flex; overflow-x: auto; gap: 4px; padding: 10px; }
    .admin-sidebar > div:first-child, .admin-sidebar hr { display: none; }
    .admin-nav-item { white-space: nowrap; border-left: none; border-radius: 99px; padding: 8px 14px; }
    .admin-nav-item.activo { background: var(--primary); color: #fff; }
    .admin-content { padding: 18px 16px; }
}
</style>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
