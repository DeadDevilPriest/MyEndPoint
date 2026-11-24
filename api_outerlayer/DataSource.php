interface DataSource {
    public function find(Query $q): iterable;   // iterable → Generators möglich
    public function findOne(string $source, $id): ?array;
    public function insert(string $source, array $data): string|int;
    public function update(string $source, $id, array $data): bool;
    public function delete(string $source, $id): bool;
    public function capabilities(): array;      // ['transactions' => false, 'nativeFilter' => true, ...]
}