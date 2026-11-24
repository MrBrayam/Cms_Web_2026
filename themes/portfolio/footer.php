</div></main>
<footer class="pf-footer">
    <div class="pf-container pf-footer-inner">
        <span>&copy; <?php echo date('Y'); ?> <?php echo Security::e($siteTitle); ?> &mdash; Tema Portfolio</span>
        <div style="display:flex;gap:1.2rem;">
            <?php foreach ($footerMenu as $item): ?>
                <a href="<?php echo Security::e(Helpers::url($item['url'])); ?>"><?php echo Security::e($item['label']); ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</footer>
<?php PluginManager::doAction('footer_scripts'); ?>
</body>
</html>
