/* Public-only, versioned IndexedDB plumbing. No profile or mutation queues in P1. */
export const privateOfflineEnabled = false;
const dbName = () => 'iedpu-public-metadata:' + new URL(document.baseURI).origin + ':' + new URL(document.getElementById('app').dataset.baseUrl).pathname;
export async function initialisePublicStorage() {
    if (!window.Dexie || !window.indexedDB) return false;
    const db = new window.Dexie(dbName());
    db.version(1).stores({metadata:'key'});
    try {
        await db.open();
        await db.metadata.put({key:'schema', value:1});
        return true;
    } finally { db.close(); }
}
export async function clearPublicStorage() {
    if (!window.Dexie) return;
    await window.Dexie.delete(dbName());
}
