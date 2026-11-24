<div class="pf-article-wrap">
    <?php if (!empty($flash)): ?>
        <div class="pf-flash <?php echo $flash['type'] === 'ok' ? 'pf-flash-ok' : 'pf-flash-err'; ?>"><?php echo Security::e($flash['message']); ?></div>
    <?php endif; ?>
    <h1 style="color:#fff;font-size:2.1rem;margin:0 0 1rem;"><?php echo Security::e($page['title']); ?></h1>
    <div><?php echo $renderedContent; ?></div>
</div>
