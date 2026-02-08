<?php
declare(strict_types=1);

final class SimplePager
{
    public int $total;
    public int $perPage;
    public int $page;
    public int $pages;

    public function __construct(int $total, int $page, int $perPage)
    {
        $this->total = max(0, $total);
        $this->perPage = max(1, $perPage);
        $this->pages = max(1, (int)ceil($this->total / $this->perPage));
        $this->page = min(max(1, $page), $this->pages);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function limit(): int
    {
        return $this->perPage;
    }

    public function hasPrev(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->pages;
    }

    public function prevPage(): int
    {
        return max(1, $this->page - 1);
    }

    public function nextPage(): int
    {
        return min($this->pages, $this->page + 1);
    }

    public function window(int $radius = 2): array
    {
        $radius = max(0, $radius);
        $start = max(1, $this->page - $radius);
        $end = min($this->pages, $this->page + $radius);
        $out = [];
        for ($i = $start; $i <= $end; $i++) {
            $out[] = $i;
        }
        return $out;
    }

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'per_page' => $this->perPage,
            'page' => $this->page,
            'pages' => $this->pages,
            'has_prev' => $this->hasPrev(),
            'has_next' => $this->hasNext(),
            'prev_page' => $this->prevPage(),
            'next_page' => $this->nextPage(),
        ];
    }
}

