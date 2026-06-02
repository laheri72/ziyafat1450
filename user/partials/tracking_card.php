<div class="card" data-dua-id="<?php echo $item['id']; ?>" style="display: flex; flex-direction: column;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: flex-start; padding: 12px 15px !important;">
        <div style="flex: 1;">
            <h3 style="font-size: 1rem; margin: 0;"><?php echo htmlspecialchars($item['dua_name']); ?></h3>
            <p dir="rtl" style="font-size: 16px; color: #888; margin-top: 4px;"><?php echo htmlspecialchars($item['dua_name_arabic']); ?></p>
        </div>
        <a href="dua_history.php?dua_id=<?php echo $item['id']; ?>" 
           class="btn btn-sm btn-outline" 
           style="padding: 5px 8px; border-width: 1px; font-size: 0.75rem;" 
           title="View History">
            <i class="fas fa-history"></i>
        </a>
    </div>
    
    <div class="progress-container" style="padding: 12px 15px 0 15px;">
        <div class="progress-label" style="margin-bottom: 6px;">
            <span class="progress-label-text" style="font-size: 0.8rem;">Total: <strong><?php echo $item['completed_count']; ?></strong> / <?php echo $item['target_count']; ?></span>
            <span class="progress-label-value" style="font-size: 0.85rem;"><?php echo $item['progress_percentage']; ?>%</span>
        </div>
        <div class="progress-bar" style="height: 8px;">
            <div class="progress-fill" style="width: <?php echo min($item['progress_percentage'], 100); ?>%"></div>
        </div>
    </div>

    <form class="tracking-form" style="padding: 15px;">
        <input type="hidden" name="dua_id" value="<?php echo $item['id']; ?>">
        <input type="hidden" name="entry_date" value="<?php echo date('Y-m-d'); ?>">
        
        <div class="form-group" style="margin-bottom: 12px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <input type="number" 
                       id="count_to_add_<?php echo $item['id']; ?>" 
                       name="count_to_add" 
                       class="form-control" 
                       min="1" 
                       inputmode="numeric"
                       pattern="[0-9]*"
                       placeholder="Add count"
                       style="padding: 8px 12px; font-size: 14px;"
                       required>
                <button type="submit" class="btn btn-primary" style="padding: 8px 20px; white-space: nowrap;">
                    <i class="fas fa-plus"></i> Add
                </button>
            </div>
            <?php if ($item['last_updated']): ?>
                <p style="font-size: 10px; color: #999; margin-top: 6px; margin-bottom: 0;">
                    <i class="far fa-clock"></i> Last entry: <?php echo date('M d', strtotime($item['last_updated'])); ?>
                </p>
            <?php endif; ?>
        </div>
    </form>
</div>