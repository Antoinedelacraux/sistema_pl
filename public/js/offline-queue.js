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

    // UUID v4 para idempotencia (client_request_id): se genera una vez por registro encolado
    // y se reenvía igual en cada reintento, así el servidor no lo inserta dos veces.
    function requestId() {
        var c = window.crypto || window.msCrypto;
        if (c && typeof c.randomUUID === 'function') {
            return c.randomUUID();
        }
        var bytes = new Uint8Array(16);
        if (c && typeof c.getRandomValues === 'function') {
            c.getRandomValues(bytes);
        } else {
            for (var i = 0; i < 16; i++) {
                bytes[i] = Math.floor(Math.random() * 256);
            }
        }
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        var hex = Array.prototype.map.call(bytes, function (b) {
            return ('0' + b.toString(16)).slice(-2);
        }).join('');
        return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
    }

    window.OfflineQueue = {
        requestId: requestId,
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
