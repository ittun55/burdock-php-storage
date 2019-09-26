<?php
namespace Burdock\CloudStorage;

interface StorageAdapterInterface
{
    public function getFullPath(string $path): string;
    public function getList(string $path, int $depth): array;
    public function download(string $src, string $dst): string;
    public function upload(string $src, string $dst): bool;
    public function delete(string $remote): bool;
    public function createFolder(string $path): bool;
}