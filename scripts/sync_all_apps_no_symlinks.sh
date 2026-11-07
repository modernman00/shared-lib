#!/usr/bin/env bash
set -euo pipefail

BASE_DIR="/Users/waleolaogun/Sites"
TARGETS=("iaccountapp" "idecideapp" "execmindapp" "familyPlatform" "partyplatform" "loaneasyfinance" "scoretenant" "bcp")
SHARED_LIB="$BASE_DIR/shared-lib"
SHARED_JS_LIB="$BASE_DIR/shared-js-lib"

echo "======================================================================"
echo "  🚀 PHYSICAL SYNC (NO SYMLINKS) ACROSS ALL 8 APPLICATIONS"
echo "======================================================================"

for app in "${TARGETS[@]}"; do
    app_path="$BASE_DIR/$app"
    if [[ ! -d "$app_path" ]]; then
        echo "⚠️ Skipping missing directory: $app_path"
        continue
    fi

    echo -e "\n📦 Syncing Application: [$app]..."

    # 1. PHP shared-lib (vendor/modernman00/shared-lib)
    vendor_pkg_dir="$app_path/vendor/modernman00/shared-lib"
    if [[ -L "$vendor_pkg_dir" ]]; then
        echo "  🔗 Removing symlink: $vendor_pkg_dir"
        rm "$vendor_pkg_dir"
    fi
    mkdir -p "$vendor_pkg_dir"
    echo "  📂 Rsyncing physical shared-lib files into $vendor_pkg_dir..."
    rsync -a --delete \
        --exclude='.git' \
        --exclude='.phpunit.cache' \
        --exclude='node_modules' \
        "$SHARED_LIB/" "$vendor_pkg_dir/"

    # 2. JS shared-js-lib (node_modules/@modernman00/shared-js-lib)
    if [[ -d "$app_path/node_modules" ]]; then
        node_pkg_dir="$app_path/node_modules/@modernman00/shared-js-lib"
        if [[ -L "$node_pkg_dir" ]]; then
            echo "  🔗 Removing symlink: $node_pkg_dir"
            rm "$node_pkg_dir"
        fi
        mkdir -p "$node_pkg_dir"
        echo "  📂 Rsyncing physical shared-js-lib files into $node_pkg_dir..."
        rsync -a --delete \
            --exclude='.git' \
            --exclude='node_modules' \
            "$SHARED_JS_LIB/" "$node_pkg_dir/"
    fi

    # 3. Public JS engine (public/js/universal-poster-engine.js)
    if [[ -d "$app_path/public/js" ]]; then
        echo "  📄 Copying public/js/universal-poster-engine.js..."
        cp -f "$SHARED_LIB/helpers/universal-poster-engine.js" "$app_path/public/js/universal-poster-engine.js"
    fi

    echo "  ✅ Physical sync completed for $app"
done

echo -e "\n======================================================================"
echo "  🎉 ALL 8 APPS UPDATED WITH PHYSICAL STANDALONE FILES (ZERO SYMLINKS)!"
echo "======================================================================"
