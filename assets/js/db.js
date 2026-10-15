/**
 * assets/js/db.js
 * Client-Side Database Engine & LocalStorage Manager for Gindaco SPK
 * Ported for pure Static HTML/CSS/JS deployment on GitHub Pages & Vercel
 */

(function(window) {
    'use strict';

    const DB_KEYS = {
        BAHAN: 'gindaco_bahan_baku',
        MENU: 'gindaco_menu',
        BOM: 'gindaco_bom_recipes',
        HISTORIS: 'gindaco_historis',
        PENJUALAN: 'gindaco_penjualan',
        USERS: 'gindaco_users',
        CURRENT_USER: 'gindaco_current_user',
        SPK_MATERIALS: 'gindaco_spk_materials',
        SPK_CONFIG: 'gindaco_spk_config',
        INITIALIZED: 'gindaco_db_initialized_v2'
    };

    // POS Article Code mapping ke Menu ID (Ported from includes/bom_service.php)
    const POS_CODE_TO_MENU = {
        '10622408': 1,  '10622409': 4,  '10622410': 7,  '10622411': 10,
        '10622412': 2,  '10622413': 5,  '10622414': 8,  '10622415': 11,
        '10622404': 3,  '10622405': 6,  '10622406': 9,  '10622407': 12,
        '10698023': 109,'10698024': 110,'10698025': 111,'10698026': 112,
        '10622416': 13, '10622417': 14, '10622418': 15,
        '10698027': 113,'10698028': 114,'10698029': 115,
        '10515225': 61, '10515226': 62, '10515227': 86, '10698031': 86,
        '10590982': 43, '10590983': 107,'10590984': 43, '10698030': 107
    };

    const ADDONS_MAPPING = {
        '10460034': { kode: '10420731', m: 1.0 }, // Spunbond
        '10592691': { kode: '10591374', m: 1.0 }, // Box Taiyaki
        '10521058': { kode: '10502591', m: 1.0 }, // Air Mineral 600ml
        '10238765': { kode: '10175143', m: 15.0 }, // Takoyaki Sauce
        '10238766': { kode: '10175144', m: 15.0 }, // Teriyaki Sauce
        '10238767': { kode: '10175666', m: 15.0 }, // Mayonaise Kewpie
        '10238768': { kode: '10175140', m: 2.0 },  // Fish Shaving
        '10238769': { kode: '10432429', m: 25.0 }, // Tamagoyaki Egg
        '10238770': { kode: '10184385', m: 10.0 }, // Mozzarella
        '10238771': { kode: '10184382', m: 10.0 }  // Mentaiko
    };

    const DEFAULT_USERS = [
        { id: 1, nik: 'manager', nama: 'Manager Outlet MAG', password: 'password', level: 'manager' },
        { id: 2, nik: 'staff', nama: 'Staff Inventory Gindaco', password: 'password', level: 'staff' },
        { id: 3, nik: 'admin', nama: 'Administrator Artha Gading', password: 'admin', level: 'manager' }
    ];

    const GindacoDB = {
        // Inisialisasi Database LocalStorage dari seed jika belum ada
        init: function(forceReset = false) {
            const isInitialized = localStorage.getItem(DB_KEYS.INITIALIZED);
            if (!isInitialized || forceReset) {
                const seed = window.GINDACO_SEED || { bahan_baku: [], bom_recipes: {}, menu: [], historis: [], penjualan: [] };
                
                localStorage.setItem(DB_KEYS.BAHAN, JSON.stringify(seed.bahan_baku || []));
                localStorage.setItem(DB_KEYS.MENU, JSON.stringify(seed.menu || []));
                localStorage.setItem(DB_KEYS.BOM, JSON.stringify(seed.bom_recipes || {}));
                localStorage.setItem(DB_KEYS.HISTORIS, JSON.stringify(seed.historis || []));
                localStorage.setItem(DB_KEYS.PENJUALAN, JSON.stringify(seed.penjualan || []));
                localStorage.setItem(DB_KEYS.USERS, JSON.stringify(DEFAULT_USERS));

                // Default config SPK
                localStorage.setItem(DB_KEYS.SPK_CONFIG, JSON.stringify({
                    alpha: 0.3,
                    buffer_unit: 'days',
                    buffer_value: 1.0,
                    updated_at: new Date().toISOString()
                }));

                // Default active user jika belum login
                if (!localStorage.getItem(DB_KEYS.CURRENT_USER)) {
                    localStorage.setItem(DB_KEYS.CURRENT_USER, JSON.stringify(DEFAULT_USERS[0]));
                }

                localStorage.setItem(DB_KEYS.INITIALIZED, 'true');
                console.log('GindacoDB: LocalStorage initialized successfully from seed.');
            }
        },

        // BAHAN BAKU CRUD
        getBahanBaku: function() {
            this.init();
            return JSON.parse(localStorage.getItem(DB_KEYS.BAHAN) || '[]');
        },

        getBahanById: function(id) {
            const list = this.getBahanBaku();
            return list.find(b => b.id === parseInt(id, 10)) || null;
        },

        getBahanByKode: function(kode) {
            const list = this.getBahanBaku();
            return list.find(b => String(b.kode_bahan).trim() === String(kode).trim()) || null;
        },

        saveBahanBaku: function(items) {
            localStorage.setItem(DB_KEYS.BAHAN, JSON.stringify(items));
        },

        addBahanBaku: function(item) {
            const list = this.getBahanBaku();
            const maxId = list.reduce((max, b) => Math.max(max, b.id || 0), 0);
            const newItem = {
                id: maxId + 1,
                kode_bahan: item.kode_bahan.trim(),
                nama_bahan: item.nama_bahan.trim(),
                satuan: item.satuan.trim(),
                stok_aktual: parseFloat(item.stok_aktual) || 0,
                safety_stock: parseFloat(item.safety_stock) || 0
            };
            list.push(newItem);
            this.saveBahanBaku(list);
            return newItem;
        },

        updateBahanBaku: function(id, data) {
            const list = this.getBahanBaku();
            const idx = list.findIndex(b => b.id === parseInt(id, 10));
            if (idx !== -1) {
                list[idx].kode_bahan = data.kode_bahan.trim();
                list[idx].nama_bahan = data.nama_bahan.trim();
                list[idx].satuan = data.satuan.trim();
                list[idx].stok_aktual = parseFloat(data.stok_aktual) || 0;
                list[idx].safety_stock = parseFloat(data.safety_stock) || 0;
                this.saveBahanBaku(list);
                return list[idx];
            }
            return null;
        },

        deleteBahanBaku: function(id) {
            let list = this.getBahanBaku();
            list = list.filter(b => b.id !== parseInt(id, 10));
            this.saveBahanBaku(list);
            return true;
        },

        // HISTORIS CRUD
        getHistoris: function() {
            this.init();
            return JSON.parse(localStorage.getItem(DB_KEYS.HISTORIS) || '[]');
        },

        addHistorisBatch: function(items) {
            const list = this.getHistoris();
            let maxId = list.reduce((max, h) => Math.max(max, h.id || 0), 0);
            items.forEach(it => {
                maxId++;
                list.push({
                    id: maxId,
                    id_bahan: parseInt(it.id_bahan, 10),
                    periode: it.periode,
                    pemakaian_aktual: parseFloat(it.pemakaian_aktual) || 0
                });
            });
            localStorage.setItem(DB_KEYS.HISTORIS, JSON.stringify(list));
            return true;
        },

        // USER & AUTHENTICATION
        getUsers: function() {
            this.init();
            return JSON.parse(localStorage.getItem(DB_KEYS.USERS) || '[]');
        },

        getCurrentUser: function() {
            this.init();
            const u = localStorage.getItem(DB_KEYS.CURRENT_USER);
            return u ? JSON.parse(u) : DEFAULT_USERS[0];
        },

        setCurrentUser: function(user) {
            if (user) {
                localStorage.setItem(DB_KEYS.CURRENT_USER, JSON.stringify(user));
            } else {
                localStorage.removeItem(DB_KEYS.CURRENT_USER);
            }
        },

        login: function(nik, password) {
            const users = this.getUsers();
            const found = users.find(u => u.nik.toLowerCase() === nik.trim().toLowerCase() && u.password === password);
            if (found) {
                this.setCurrentUser(found);
                return { success: true, user: found };
            }
            return { success: false, message: 'NIK atau Password tidak sesuai!' };
        },

        register: function(nik, nama, password, level = 'staff') {
            const users = this.getUsers();
            if (users.some(u => u.nik.toLowerCase() === nik.trim().toLowerCase())) {
                return { success: false, message: 'NIK sudah terdaftar dalam sistem!' };
            }
            const maxId = users.reduce((max, u) => Math.max(max, u.id || 0), 0);
            const newUser = {
                id: maxId + 1,
                nik: nik.trim(),
                nama: nama.trim(),
                password: password,
                level: level
            };
            users.push(newUser);
            localStorage.setItem(DB_KEYS.USERS, JSON.stringify(users));
            return { success: true, user: newUser };
        },

        logout: function() {
            localStorage.removeItem(DB_KEYS.CURRENT_USER);
        },

        // SPK RECIPE EXPLOSION & WEEKLY FORECAST ENGINE
        getBOMRecipes: function() {
            this.init();
            return JSON.parse(localStorage.getItem(DB_KEYS.BOM) || '{}');
        },

        getMenus: function() {
            this.init();
            return JSON.parse(localStorage.getItem(DB_KEYS.MENU) || '[]');
        },

        resolveItemToBOM: function(article_code, article_name) {
            const code = String(article_code || '').trim();
            const nameClean = String(article_name || '').trim();
            const nameUpper = nameClean.toUpperCase();

            // 1. Cek langsung tabel menu berdasarkan kode_menu
            const menus = this.getMenus();
            const menuFound = menus.find(m => String(m.kode_menu).trim() === code);
            if (menuFound) {
                return { type: 'menu', id: menuFound.id };
            }

            // 2. Pemetaan kode artikel mesin POS Gindaco ke ID Menu
            if (POS_CODE_TO_MENU[code]) {
                return { type: 'menu', id: POS_CODE_TO_MENU[code] };
            }

            // 3. Cek langsung bahan baku (packaging/kemasan)
            const bahanList = this.getBahanBaku();
            const bbFound = bahanList.find(b => String(b.kode_bahan).trim() === code);
            if (bbFound) {
                return { type: 'bahan_direct', kode_bahan: code, qty_multiplier: 1.0 };
            }

            // 4. Perlengkapan & Add-on langsung
            if (ADDONS_MAPPING[code]) {
                return { type: 'bahan_direct', kode_bahan: ADDONS_MAPPING[code].kode, qty_multiplier: ADDONS_MAPPING[code].m };
            }

            // 5. Pencocokan berbasis teks nama menu Takoyaki
            if (nameUpper.includes('ORIGINAL')) {
                if (nameUpper.includes('4')) return { type: 'menu', id: 1 };
                if (nameUpper.includes('6')) return { type: 'menu', id: 4 };
                if (nameUpper.includes('8')) return { type: 'menu', id: 7 };
                if (nameUpper.includes('16') || nameUpper.includes('PARTY')) return { type: 'menu', id: 10 };
            }
            if (nameUpper.includes('TERITAMA')) {
                if (nameUpper.includes('4')) return { type: 'menu', id: 2 };
                if (nameUpper.includes('6')) return { type: 'menu', id: 5 };
                if (nameUpper.includes('8')) return { type: 'menu', id: 8 };
                if (nameUpper.includes('16') || nameUpper.includes('PARTY')) return { type: 'menu', id: 11 };
            }
            if (nameUpper.includes('MENTAIKO')) {
                if (nameUpper.includes('4')) return { type: 'menu', id: 3 };
                if (nameUpper.includes('6')) return { type: 'menu', id: 6 };
                if (nameUpper.includes('8')) return { type: 'menu', id: 9 };
                if (nameUpper.includes('16') || nameUpper.includes('PARTY')) return { type: 'menu', id: 12 };
            }
            if (nameUpper.includes('TRIPLE CHEESE')) {
                if (nameUpper.includes('4')) return { type: 'menu', id: 109 };
                if (nameUpper.includes('6')) return { type: 'menu', id: 110 };
                if (nameUpper.includes('8')) return { type: 'menu', id: 111 };
                if (nameUpper.includes('16') || nameUpper.includes('PARTY')) return { type: 'menu', id: 112 };
            }

            // 6. Pencocokan nama bahan langsung
            const bbByName = bahanList.find(b => nameClean.toLowerCase().includes(b.nama_bahan.toLowerCase()) || b.nama_bahan.toLowerCase().includes(nameClean.toLowerCase()));
            if (bbByName) {
                return { type: 'bahan_direct', kode_bahan: bbByName.kode_bahan, qty_multiplier: 1.0 };
            }

            return null;
        },

        // Recipe Explosion: Konversi Menu Penjualan Mingguan ke Konsumsi Bahan Baku
        explodeMenuSalesToIngredients: function(menuSales) {
            const bomRecipes = this.getBOMRecipes();
            const allRaw = this.getBahanBaku();

            const ingredientUsage = {};
            allRaw.forEach(b => {
                ingredientUsage[b.kode_bahan] = 0.0;
            });

            for (const articleCode in menuSales) {
                const item = menuSales[articleCode];
                const qtySold = parseFloat(item.qty || 0);
                if (qtySold <= 0) continue;

                const resolved = this.resolveItemToBOM(articleCode, item.name || '');
                if (!resolved) continue;

                if (resolved.type === 'menu') {
                    const idMenu = resolved.id;
                    const recipe = bomRecipes[idMenu] || [];
                    recipe.forEach(bomItem => {
                        const kb = bomItem.kode_bahan;
                        const butuh = (parseFloat(bomItem.qty_dibutuhkan) || 0) * qtySold;
                        if (ingredientUsage[kb] === undefined) ingredientUsage[kb] = 0.0;
                        ingredientUsage[kb] += butuh;
                    });
                } else if (resolved.type === 'bahan_direct') {
                    const kb = resolved.kode_bahan;
                    const mult = parseFloat(resolved.qty_multiplier || 1.0);
                    const butuh = qtySold * mult;
                    if (ingredientUsage[kb] === undefined) ingredientUsage[kb] = 0.0;
                    ingredientUsage[kb] += butuh;
                }
            }

            return ingredientUsage;
        },

        // Proses kalkulasi Pure Weekly Smoothing & Dynamic Safety Stock pada level Bahan Baku
        processWeeklySPK: function(menuW1, menuW2, alpha = 0.3, bufferUnit = 'days', bufferValue = 1.0) {
            const bahanW1 = this.explodeMenuSalesToIngredients(menuW1);
            const bahanW2 = this.explodeMenuSalesToIngredients(menuW2);
            const allRaw = this.getBahanBaku();

            const spkMaterials = [];
            let totalOrderItems = 0;
            let totalQtyOrder = 0.0;
            let totalW1 = 0.0;
            let totalW2 = 0.0;

            allRaw.forEach(b => {
                const kb = b.kode_bahan;
                const aktualLalu = bahanW1[kb] || 0.0;
                const aktualSekarang = bahanW2[kb] || 0.0;
                const ramalanLalu = aktualLalu;

                const rawForecast = (alpha * aktualSekarang) + ((1.0 - alpha) * ramalanLalu);
                const forecastSistem = parseFloat(rawForecast.toFixed(2));
                const finalForecast = forecastSistem;
                const stokAkhir = parseFloat(b.stok_aktual || 0);

                // Dynamic Safety Stock
                const dailyUsage = finalForecast / 7.0;
                let dynamicSafeStock = 0;
                if (bufferUnit === 'percent') {
                    dynamicSafeStock = Math.round(finalForecast * (bufferValue / 100.0));
                } else {
                    dynamicSafeStock = Math.round(dailyUsage * bufferValue);
                }

                // Target Total Stok = Forecast + Safety Stock Dinamis
                const targetStok = parseFloat((finalForecast + dynamicSafeStock).toFixed(2));

                // Rekomendasi Order = Target - Stok Fisik (Min: 0)
                let saranOrder = targetStok - stokAkhir;
                if (saranOrder < 0) saranOrder = 0.0;
                saranOrder = parseFloat(saranOrder.toFixed(2));

                const isOrder = (saranOrder > 0);
                if (isOrder) totalOrderItems++;
                totalQtyOrder += saranOrder;
                totalW1 += aktualLalu;
                totalW2 += aktualSekarang;

                spkMaterials.push({
                    id: b.id,
                    kode_bahan: b.kode_bahan,
                    nama_bahan: b.nama_bahan,
                    satuan: b.satuan,
                    aktual_lalu: parseFloat(aktualLalu.toFixed(2)),
                    ramalan_lalu: parseFloat(ramalanLalu.toFixed(2)),
                    aktual_sekarang: parseFloat(aktualSekarang.toFixed(2)),
                    forecast_sistem: forecastSistem,
                    manual_override: null,
                    final_forecast: finalForecast,
                    daily_usage: parseFloat(dailyUsage.toFixed(2)),
                    dynamic_safety_stock: dynamicSafeStock,
                    target_stok: targetStok,
                    stok_fisik: stokAkhir,
                    saran_order: saranOrder,
                    order_final: saranOrder,
                    is_order: isOrder
                });
            });

            const state = {
                materials: spkMaterials,
                alpha: alpha,
                buffer_unit: bufferUnit,
                buffer_value: bufferValue,
                stats: {
                    total_materials: spkMaterials.length,
                    total_order_items: totalOrderItems,
                    total_qty_order: parseFloat(totalQtyOrder.toFixed(2)),
                    total_w1: parseFloat(totalW1.toFixed(2)),
                    total_w2: parseFloat(totalW2.toFixed(2))
                },
                updated_at: new Date().toISOString()
            };

            this.saveSPKState(state);
            return state;
        },

        getSPKState: function() {
            this.init();
            const raw = localStorage.getItem(DB_KEYS.SPK_MATERIALS);
            if (!raw) {
                // Inisialisasi baseline langsung dari master data bahan baku jika belum ada sesi weekly
                const allRaw = this.getBahanBaku();
                const materials = allRaw.map(b => ({
                    id: b.id,
                    kode_bahan: b.kode_bahan,
                    nama_bahan: b.nama_bahan,
                    satuan: b.satuan,
                    aktual_lalu: 0.0,
                    ramalan_lalu: 0.0,
                    aktual_sekarang: 0.0,
                    forecast_sistem: 0.0,
                    manual_override: null,
                    final_forecast: 0.0,
                    daily_usage: 0.0,
                    dynamic_safety_stock: 0.0,
                    target_stok: 0.0,
                    stok_fisik: parseFloat(b.stok_aktual || 0),
                    saran_order: 0.0,
                    order_final: 0.0,
                    is_order: false
                }));
                const state = {
                    materials: materials,
                    alpha: 0.3,
                    buffer_unit: 'days',
                    buffer_value: 1.0,
                    stats: {
                        total_materials: materials.length,
                        total_order_items: 0,
                        total_qty_order: 0.0,
                        total_w1: 0.0,
                        total_w2: 0.0
                    },
                    updated_at: new Date().toISOString()
                };
                return state;
            }
            return JSON.parse(raw);
        },

        saveSPKState: function(state) {
            localStorage.setItem(DB_KEYS.SPK_MATERIALS, JSON.stringify(state));
        }
    };

    // Auto initialize on script load
    GindacoDB.init();

    window.GindacoDB = GindacoDB;

})(window);

