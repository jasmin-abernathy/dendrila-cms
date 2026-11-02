# SQLite settings transaction failure

Saving SEO topics or appearance after native installation raised “There is no active transaction”. BEGIN IMMEDIATE was issued as SQL but completion used PDO::commit(), whose transaction tracking does not register this SQL transaction. Error cleanup also skipped rollback because PDO::inTransaction() returned false.

Pair owned SQLite transactions with SQL COMMIT and ROLLBACK. Keep MySQL and caller-owned PDO transactions unchanged. Existing installation tests now pass; added checks cover releasing the write lock after stale SEO/appearance forms and preserving an outer SEO transaction that is later rolled back.

Validation: all 20 composer test scripts executed successfully with the local PHP 8.3 CLI and project PSR-4 fallback autoloader. This does not replace the required PHP 8.5 CI run. No production deployment.

## Environment recovery

Private Booking git clone lacked CLI credentials: use the authenticated GitHub connector, without copying credentials. System PHP installation failed on package/cache permissions: extracted official Ubuntu PHP packages locally instead. Session path was absent in that standalone runtime: set session.save_path=/tmp in the local test wrapper. A first appearance-file lookup used the wrong directory: locate it with rg before reading. These runtime files are not committed.
