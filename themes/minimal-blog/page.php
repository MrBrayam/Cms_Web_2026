<article>
    <?php if (!empty($flash)): ?>
        <div class="mb-flash <?php echo $flash['type'] === 'ok' ? 'mb-flash-ok' : 'mb-flash-err'; ?>"><?php echo Security::e($flash['message']); ?></div>
    <?php endif; ?>
    <h1 class="mb-title" style="font-size:2.3rem;"><?php echo Security::e($page['title']); ?></h1>
    <div><?php echo $renderedContent; ?></div>
</article>
