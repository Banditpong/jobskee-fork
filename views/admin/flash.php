<!-- flash.php -->
<?php if (isset($flash['danger']) && $flash['danger'][0] != ''): ?>
    <div class="alert alert-danger"><?php _e($flash['danger'][0]); ?></div>
<?php elseif (isset($flash['success']) && $flash['success'][0] != ''): ?>
    <div class="alert alert-success"><?php _e($flash['success'][0]); ?></div>
<?php endif; ?>