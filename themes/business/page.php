<div class="bz-container">
    <div class="bz-page-wrap">
        <?php if (!empty($flash)): ?>
            <div class="bz-flash <?php echo $flash['type'] === 'ok' ? 'bz-flash-ok' : 'bz-flash-err'; ?>"><?php echo Security::e($flash['message']); ?></div>
        <?php endif; ?>
        <h1 style="color:#0b3b60;font-size:2.1rem;margin:0 0 1rem;"><?php echo Security::e($page['title']); ?></h1>
        <div><?php echo $renderedContent; ?></div>
    </div>
</div>
