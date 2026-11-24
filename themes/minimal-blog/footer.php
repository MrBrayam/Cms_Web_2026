</div></main>
<footer class="mb-footer">
    <div class="mb-container mb-footer-inner">
        <span>&copy; <?php echo date('Y'); ?> <?php echo Security::e($siteTitle); ?> &mdash; Tema Minimal Blog</span>
        <nav class="mb-nav" aria-label="Menu pie">
            <ul>
                <?php foreach ($footerMenu as $item): ?>
                    <li><a href="<?php echo Security::e(Helpers::url($item['url'])); ?>"><?php echo Security::e($item['label']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </div>
</footer>
<?php PluginManager::doAction('footer_scripts'); ?>
</body>
</html>
