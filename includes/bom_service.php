<?php
/**
 * includes/bom_service.php
 * Layanan Sentral Recipe Explosion & Pemetaan POS / Menu ke Bahan Baku (BOM)
 */

if (!defined('BOM_SERVICE_LOADED')) {
    define('BOM_SERVICE_LOADED', true);

    /**
     * Cache BOM memory dalam request untuk performa tinggi
     */
    function get_all_bom_recipes($conn) {
        static $bom_cache = null;
        if ($bom_cache !== null) return $bom_cache;

        $bom_cache = [];
        $sql = "SELECT mb.id_menu, mb.kode_bahan, mb.qty_dibutuhkan, b.nama_bahan, b.satuan, b.stok_aktual, b.safety_stock 
                FROM menu_bom mb 
                JOIN bahan_baku b ON mb.kode_bahan = b.kode_bahan";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $id_menu = (int)$row['id_menu'];
                if (!isset($bom_cache[$id_menu])) {
                    $bom_cache[$id_menu] = [];
                }
                $bom_cache[$id_menu][] = [
                    'kode_bahan' => $row['kode_bahan'],
                    'qty_dibutuhkan' => (float)$row['qty_dibutuhkan'],
                    'nama_bahan' => $row['nama_bahan'],
                    'satuan' => $row['satuan'],
                    'stok_aktual' => (float)$row['stok_aktual'],
                    'safety_stock' => (float)$row['safety_stock']
                ];
            }
        }
        return $bom_cache;
    }

    /**
     * Cache daftar seluruh bahan baku aktif
     */
    function get_all_raw_materials($conn) {
        static $bahan_cache = null;
        if ($bahan_cache !== null) return $bahan_cache;

        $bahan_cache = [];
        $res = $conn->query("SELECT * FROM bahan_baku ORDER BY nama_bahan ASC");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $bahan_cache[$r['kode_bahan']] = [
                    'id' => (int)$r['id'],
                    'kode_bahan' => $r['kode_bahan'],
                    'nama_bahan' => $r['nama_bahan'],
                    'satuan' => $r['satuan'],
                    'stok_aktual' => (float)$r['stok_aktual'],
                    'safety_stock' => (float)$r['safety_stock']
                ];
            }
        }
        return $bahan_cache;
    }

    /**
     * Resolusi kode artikel / produk jadi POS ke Menu ID atau Bahan Baku Langsung
     */
    function resolveItemToBOM($conn, $article_code, $article_name) {
        $article_code = trim((string)$article_code);
        $name_clean = trim((string)$article_name);
        $name_upper = strtoupper($name_clean);

        // 1. Cek langsung tabel menu berdasarkan kode_menu
        $q_menu = $conn->query("SELECT id FROM menu WHERE kode_menu = '$article_code' LIMIT 1");
        if ($q_menu && $q_menu->num_rows > 0) {
            return ['type' => 'menu', 'id' => (int)$q_menu->fetch_assoc()['id']];
        }

        // 2. Pemetaan kode artikel mesin POS Gindaco ke ID Menu
        $pos_code_to_menu = [
            '10622408' => 1,  '10622409' => 4,  '10622410' => 7,  '10622411' => 10,
            '10622412' => 2,  '10622413' => 5,  '10622414' => 8,  '10622415' => 11,
            '10622404' => 3,  '10622405' => 6,  '10622406' => 9,  '10622407' => 12,
            '10698023' => 109,'10698024' => 110,'10698025' => 111,'10698026' => 112,
            '10622416' => 13, '10622417' => 14, '10622418' => 15,
            '10698027' => 113,'10698028' => 114,'10698029' => 115,
            '10515225' => 61, '10515226' => 62, '10515227' => 86, '10698031' => 86,
            '10590982' => 43, '10590983' => 107,'10590984' => 43, '10698030' => 107
        ];
        if (isset($pos_code_to_menu[$article_code])) {
            return ['type' => 'menu', 'id' => $pos_code_to_menu[$article_code]];
        }

        // 3. Cek langsung bahan baku (packaging/kemasan)
        $q_bb = $conn->query("SELECT kode_bahan FROM bahan_baku WHERE kode_bahan = '$article_code' LIMIT 1");
        if ($q_bb && $q_bb->num_rows > 0) {
            return ['type' => 'bahan_direct', 'kode_bahan' => $article_code, 'qty_multiplier' => 1.0];
        }

        // 4. Perlengkapan & Add-on langsung
        $addons = [
            '10460034' => ['kode' => '10420731', 'm' => 1.0], // Spunbond
            '10592691' => ['kode' => '10591374', 'm' => 1.0], // Box Taiyaki
            '10521058' => ['kode' => '10502591', 'm' => 1.0], // Air Mineral 600ml
            '10238765' => ['kode' => '10175143', 'm' => 15.0], // Takoyaki Sauce
            '10238766' => ['kode' => '10175144', 'm' => 15.0], // Teriyaki Sauce
            '10238767' => ['kode' => '10175666', 'm' => 15.0], // Mayonaise Kewpie
            '10238768' => ['kode' => '10175140', 'm' => 2.0],  // Fish Shaving
            '10238769' => ['kode' => '10432429', 'm' => 25.0], // Tamagoyaki Egg
            '10238770' => ['kode' => '10184385', 'm' => 10.0], // Mozzarella
            '10238771' => ['kode' => '10184382', 'm' => 10.0]  // Mentaiko
        ];
        if (isset($addons[$article_code])) {
            return ['type' => 'bahan_direct', 'kode_bahan' => $addons[$article_code]['kode'], 'qty_multiplier' => $addons[$article_code]['m']];
        }

        // 5. Pencocokan berbasis teks nama menu Takoyaki
        if (strpos($name_upper, 'ORIGINAL') !== false) {
            if (strpos($name_upper, '4') !== false) return ['type' => 'menu', 'id' => 1];
            if (strpos($name_upper, '6') !== false) return ['type' => 'menu', 'id' => 4];
            if (strpos($name_upper, '8') !== false) return ['type' => 'menu', 'id' => 7];
            if (strpos($name_upper, '16') !== false || strpos($name_upper, 'PARTY') !== false) return ['type' => 'menu', 'id' => 10];
        }
        if (strpos($name_upper, 'TERITAMA') !== false) {
            if (strpos($name_upper, '4') !== false) return ['type' => 'menu', 'id' => 2];
            if (strpos($name_upper, '6') !== false) return ['type' => 'menu', 'id' => 5];
            if (strpos($name_upper, '8') !== false) return ['type' => 'menu', 'id' => 8];
            if (strpos($name_upper, '16') !== false || strpos($name_upper, 'PARTY') !== false) return ['type' => 'menu', 'id' => 11];
        }
        if (strpos($name_upper, 'MENTAIKO') !== false) {
            if (strpos($name_upper, '4') !== false) return ['type' => 'menu', 'id' => 3];
            if (strpos($name_upper, '6') !== false) return ['type' => 'menu', 'id' => 6];
            if (strpos($name_upper, '8') !== false) return ['type' => 'menu', 'id' => 9];
            if (strpos($name_upper, '16') !== false || strpos($name_upper, 'PARTY') !== false) return ['type' => 'menu', 'id' => 12];
        }
        if (strpos($name_upper, 'TRIPLE CHEESE') !== false) {
            if (strpos($name_upper, '4') !== false) return ['type' => 'menu', 'id' => 109];
            if (strpos($name_upper, '6') !== false) return ['type' => 'menu', 'id' => 110];
            if (strpos($name_upper, '8') !== false) return ['type' => 'menu', 'id' => 111];
            if (strpos($name_upper, '16') !== false || strpos($name_upper, 'PARTY') !== false) return ['type' => 'menu', 'id' => 112];
        }

        // 6. Pencocokan nama bahan langsung
        $safe_name = $conn->real_escape_string($name_clean);
        $q_n = $conn->query("SELECT kode_bahan FROM bahan_baku WHERE nama_bahan LIKE '%$safe_name%' OR '$safe_name' LIKE CONCAT('%', nama_bahan, '%') LIMIT 1");
        if ($q_n && $q_n->num_rows > 0) {
            return ['type' => 'bahan_direct', 'kode_bahan' => $q_n->fetch_assoc()['kode_bahan'], 'qty_multiplier' => 1.0];
        }

        return null;
    }

    /**
     * Konversi Menu Penjualan Mingguan ke Konsumsi Bahan Baku (Recipe Explosion)
     * Input:  $menu_sales = [ 'article_code' => ['name' => ..., 'qty' => 50], ... ]
     * Output: $raw_consumption = [ 'kode_bahan' => total_pemakaian_bahan, ... ]
     */
    function explodeMenuSalesToIngredients($conn, $menu_sales) {
        $bom_recipes = get_all_bom_recipes($conn);
        $all_raw = get_all_raw_materials($conn);

        // Inisialisasi semua bahan baku dengan nilai 0.0 agar lengkap
        $ingredient_usage = [];
        foreach ($all_raw as $kb => $b) {
            $ingredient_usage[$kb] = 0.0;
        }

        foreach ($menu_sales as $article_code => $item) {
            $qty_sold = (float)($item['qty'] ?? 0);
            if ($qty_sold <= 0) continue;

            $resolved = resolveItemToBOM($conn, $article_code, $item['name'] ?? '');
            if (!$resolved) continue;

            if ($resolved['type'] === 'menu') {
                $id_menu = (int)$resolved['id'];
                if (isset($bom_recipes[$id_menu])) {
                    foreach ($bom_recipes[$id_menu] as $bom_item) {
                        $kb = $bom_item['kode_bahan'];
                        $butuh = (float)$bom_item['qty_dibutuhkan'] * $qty_sold;
                        if (!isset($ingredient_usage[$kb])) {
                            $ingredient_usage[$kb] = 0.0;
                        }
                        $ingredient_usage[$kb] += $butuh;
                    }
                }
            } elseif ($resolved['type'] === 'bahan_direct') {
                $kb = $resolved['kode_bahan'];
                $multiplier = (float)($resolved['qty_multiplier'] ?? 1.0);
                $butuh = $qty_sold * $multiplier;
                if (!isset($ingredient_usage[$kb])) {
                    $ingredient_usage[$kb] = 0.0;
                }
                $ingredient_usage[$kb] += $butuh;
            }
        }

        return $ingredient_usage;
    }
}

