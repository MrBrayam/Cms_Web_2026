</main>
<footer class="bz-footer">
    <div class="bz-container">
        <div class="bz-footer-grid">
            <div>
                <h4><?php echo Security::e($siteTitle); ?></h4>
                <p><?php echo Security::e($siteTagline); ?></p>
            </div>
            <div>
                <h4>Navegaci&oacute;n</h4>
                <ul>
                    <?php foreach ($footerMenu as $item): ?>
                        <li><a href="<?php echo Security::e(Helpers::url($item['url'])); ?>"><?php echo Security::e($item['label']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <h4>Categor&iacute;as</h4>
                <ul>
                    <?php foreach (array_slice($categories, 0, 5) as $cat): ?>
                        <li><a href="<?php echo Security::e(Helpers::url('/categoria/' . $cat['slug'])); ?>"><?php echo Security::e($cat['name']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div class="bz-footer-bottom">
            <span>&copy; <?php echo date('Y'); ?> <?php echo Security::e($siteTitle); ?> &mdash; Tema Business Corporativo</span>
            <span>UNSM &middot; Ingenier&iacute;a Web</span>
        </div>
    </div>
</footer>
<?php PluginManager::doAction('footer_scripts'); ?>
</body>
</html>
