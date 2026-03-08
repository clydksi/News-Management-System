<?php
/**
 * Paginator Class
 * Handles pagination logic for article lists
 */

class Paginator {
    private array $items;
    private int $itemsPerPage;
    private int $currentPage;
    private int $totalItems;
    private int $totalPages;
    
    public function __construct(array $items, int $currentPage = 1, int $itemsPerPage = 12) {
        $this->items = $items;
        $this->currentPage = max(1, $currentPage);
        $this->itemsPerPage = max(1, $itemsPerPage);
        $this->totalItems = count($items);
        $this->totalPages = (int) ceil($this->totalItems / $this->itemsPerPage);
        
        // Adjust current page if out of bounds
        if ($this->currentPage > $this->totalPages && $this->totalPages > 0) {
            $this->currentPage = $this->totalPages;
        }
    }
    
    /**
     * Get paginated items for current page
     * 
     * @return array Paginated items
     */
    public function getItems(): array {
        $offset = ($this->currentPage - 1) * $this->itemsPerPage;
        return array_slice($this->items, $offset, $this->itemsPerPage);
    }
    
    /**
     * Get current page number
     * 
     * @return int
     */
    public function getCurrentPage(): int {
        return $this->currentPage;
    }
    
    /**
     * Get total number of pages
     * 
     * @return int
     */
    public function getTotalPages(): int {
        return $this->totalPages;
    }
    
    /**
     * Get total number of items
     * 
     * @return int
     */
    public function getTotalItems(): int {
        return $this->totalItems;
    }
    
    /**
     * Get items per page
     * 
     * @return int
     */
    public function getItemsPerPage(): int {
        return $this->itemsPerPage;
    }
    
    /**
     * Check if there's a previous page
     * 
     * @return bool
     */
    public function hasPrevious(): bool {
        return $this->currentPage > 1;
    }
    
    /**
     * Check if there's a next page
     * 
     * @return bool
     */
    public function hasNext(): bool {
        return $this->currentPage < $this->totalPages;
    }
    
    /**
     * Get offset for current page
     * 
     * @return int
     */
    public function getOffset(): int {
        return ($this->currentPage - 1) * $this->itemsPerPage;
    }
    
    /**
     * Get range string (e.g., "1-12 of 100")
     * 
     * @return string
     */
    public function getRangeString(): string {
        if ($this->totalItems === 0) {
            return '0-0 of 0';
        }
        
        $start = $this->getOffset() + 1;
        $end = min($start + $this->itemsPerPage - 1, $this->totalItems);
        
        return "{$start}-{$end} of " . number_format($this->totalItems);
    }
    
    /**
     * Get pagination metadata
     * 
     * @return array
     */
    public function getMetadata(): array {
        return [
            'current_page' => $this->currentPage,
            'total_pages' => $this->totalPages,
            'items_per_page' => $this->itemsPerPage,
            'total_items' => $this->totalItems,
            'offset' => $this->getOffset(),
            'has_previous' => $this->hasPrevious(),
            'has_next' => $this->hasNext()
        ];
    }
}