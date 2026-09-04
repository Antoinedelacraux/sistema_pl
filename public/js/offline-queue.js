(function () {
    var DB_NAME = 'talsa-offline';
    var STORE = 'registro-queue';
    var VERSION = 1;

    function openDb() {
        return new Promise(function (resolve, reject) {
            var req = indexedDB.open(DB_NAME, VERSION);
            req.onupgradeneeded = function () {
                var db = req.result;
                if (!db.objectStoreNames.contains(STORE)) {
                    db.createObjectStore(STORE, { keyPath: 'id' });
                }
            };
            req.onsuccess = function () { resolve(req.result); };
            req.onerror = function () { reject(req.error); };
        });
    }

    function tx(mode) {
        return openDb().then(function (db) {
            return db.transaction(STORE, mode).objectStore(STORE);
        });
    }

    function uuid() {
        return 'q_' + Date.now() + '_' + Math.random().toString(36).slice(2, 9);
    }

    window.OfflineQueue = {
        add: function (item) {
            var record = Object.assign({ id: uuid(), createdAt: Date.now(), status: 'pending', error: null }, item);
            return tx('readwrite').then(function (store) {
                return new Promise(function (resolve, reject) {
                    var req = store.add(record);
                    req.onsuccess = function () { resolve(record); };
                    req.onerror = function () { reject(req.error); };
                });
            });
        },
        all: function () {
            return tx('readonly').then(function (store) {
                return new Promise(function (resolve, reject) {
                    var req = store.getAll();
                    req.onsuccess = function () {
                        resolve((req.result || []).sort(function (a, b) { return a.createdAt - b.createdAt; }));
                    };
                    req.onerror = function () { reject(req.error); };
                });
            });
        },
        pending: function () {
            return this.all().then(function (items) {
                return items.filter(function (i) { return i.status === 'pending' || i.status === 'error'; });
            });
        },
        remove: function (id) {
            return tx('readwrite').then(function (store) {
                return new Promise(function (resolve, reject) {
                    var req = store.delete(id);
                    req.onsuccess = function () { resolve(); };
                    req.onerror = function () { reject(req.error); };
                });
            });
        },
        update: function (item) {
            return tx('readwrite').then(function (store) {
                return new Promise(function (resolve, reject) {
                    var req = store.put(item);
                    req.onsuccess = function () { resolve(item); };
                    req.onerror = function () { reject(req.error); };
                });
            });
        },
        countPending: function () {
            return this.pending().then(function (items) { return items.length; });
        },
        clearAll: function () {
            return tx('readwrite').then(function (store) {
                return new Promise(function (resolve, reject) {
                    var req = store.clear();
                    req.onsuccess = function () { resolve(); };
                    req.onerror = function () { reject(req.error); };
                });
            });
        },
    };
})();
