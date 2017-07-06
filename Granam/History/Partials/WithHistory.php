<?php
namespace Granam\History\Partials;

interface WithHistory
{
    public function getHistory(): array;
}