<div class="mg-article-box">
    <?php if (!empty($flash)): ?>
        <div class="mg-flash <?php echo $flash['type'] === 'ok' ? 'mg-flash-ok' : 'mg-flash-err'; ?>"><?php echo Security::e($flash['message']); ?></div>
    <?php endif; ?>
    <h1 style="font-family:Georgia,serif;font-size:2.1rem;margin:0 0 1rem;color:#0f172a;"><?php echo Security::e($page['title']); ?></h1>
    <div><?php echo $renderedContent; ?></div>
</div>
