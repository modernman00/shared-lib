#!/usr/bin/env bash
# ==============================================================================
# ⚡ STAGE 7 & 9 FINISHER: AUTONOMOUS SELF-HEALING GATE (v5.0)
# ==============================================================================
# Commissioned by: Wally (CEO) | Enforced by: Jumoke Olaogun & TAT
#
# Runs the fast, deterministic iterative verification loop for the 10-Stage
# Autonomous Software Engine before waking the CEO:
#   1. Global Cross-Chat CPU & Mutex Queue (/tmp/portfolio_gate_mutex.lock)
#   2. Smart Incremental PHP Syntax Check (php -l on modified/untracked files)
#   3. Targeted PHPStan Level 8 Static Analysis on modified PHP files
#   4. 10 Golden Engineering Must-Haves Scanner (verify_engineering_must_haves.php)
#   5. Optional Targeted PHPUnit Test Suite (if passed as arguments)
#
# Usage:
#   bash scripts/autonomous_gate.sh [optional_phpunit_test_path ...]
# ==============================================================================

set -uo pipefail

export PATH="/opt/homebrew/bin:/usr/local/bin:$PATH"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"
APP_NAME="$(basename "$ROOT_DIR")"

# ------------------------------------------------------------------------------
# ⚡ CPU Priority Throttling & Global Cross-Chat Mutex Queue
# ------------------------------------------------------------------------------
renice -n 10 $$ >/dev/null 2>&1 || true

LOCK_DIR="/tmp/portfolio_gate_mutex.lock"
LOCK_ACQUIRED=0
WAIT_ANNOUNCED=0
WAIT_START=$(date +%s)

cleanup_gate_lock() {
    if [ "$LOCK_ACQUIRED" -eq 1 ] && [ -f "$LOCK_DIR/pid" ]; then
        if [ "$(cat "$LOCK_DIR/pid" 2>/dev/null)" = "$$" ]; then
            rm -rf "$LOCK_DIR" 2>/dev/null || true
        fi
    fi
}
trap cleanup_gate_lock EXIT INT TERM

while true; do
    if mkdir "$LOCK_DIR" 2>/dev/null; then
        echo "$$" > "$LOCK_DIR/pid"
        echo "${APP_NAME}:autonomous_gate" > "$LOCK_DIR/owner"
        date +%s > "$LOCK_DIR/timestamp"
        LOCK_ACQUIRED=1
        if [ "$WAIT_ANNOUNCED" -eq 1 ]; then
            echo -e "${GREEN}⚡ [GLOBAL QUEUE] Slot acquired after $(( $(date +%s) - WAIT_START ))s. Proceeding with autonomous gate...${NC}"
        fi
        break
    fi

    HOLDER_PID="$(cat "$LOCK_DIR/pid" 2>/dev/null || echo "")"
    HOLDER_OWNER="$(cat "$LOCK_DIR/owner" 2>/dev/null || echo "another workspace")"
    LOCK_TS="$(cat "$LOCK_DIR/timestamp" 2>/dev/null || echo "0")"
    NOW_TS=$(date +%s)

    if [ -n "$HOLDER_PID" ] && ! kill -0 "$HOLDER_PID" 2>/dev/null; then
        rm -rf "$LOCK_DIR" 2>/dev/null || true
        continue
    elif [ "$LOCK_TS" -gt 0 ] && [ $(( NOW_TS - LOCK_TS )) -gt 180 ]; then
        rm -rf "$LOCK_DIR" 2>/dev/null || true
        continue
    fi

    if [ "$WAIT_ANNOUNCED" -eq 0 ]; then
        echo -e "${YELLOW}⏳ [GLOBAL QUEUE] Another gate is active in ${HOLDER_OWNER} (PID ${HOLDER_PID:-unknown}). Queuing...${NC}"
        WAIT_ANNOUNCED=1
    fi
    sleep 1
done

echo -e "${BOLD}${BLUE}================================================================${NC}"
echo -e "${BOLD}${BLUE}  ⚡ 10-STAGE AUTONOMOUS ENGINE: ITERATIVE FINISHER GATE (v5.0) ${NC}"
echo -e "${BOLD}${BLUE}  App : ${APP_NAME}                                             ${NC}"
echo -e "${BOLD}${BLUE}================================================================${NC}"

FAILURES=0

# Collect modified/untracked PHP files
MODIFIED_PHP=()
if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    while IFS= read -r file; do
        if [ -n "$file" ] && [ -f "$file" ]; then
            MODIFIED_PHP+=("$file")
        fi
    done < <(git status --porcelain 2>/dev/null | awk '{print $NF}' | grep -E '\.php$' || true)
fi

# 1. Incremental PHP Syntax Check
echo -e "${BOLD}${CYAN}[FINISHER 1/4] Incremental PHP Syntax Check (php -l)...${NC}"
SYNTAX_ERRORS=0
if [ "${#MODIFIED_PHP[@]}" -gt 0 ]; then
    for file in "${MODIFIED_PHP[@]}"; do
        if ! php -l "$file" >/dev/null 2>&1; then
            echo -e "${RED}  ❌ Syntax Error in: $file${NC}"
            php -l "$file" || true
            SYNTAX_ERRORS=$((SYNTAX_ERRORS + 1))
        fi
    done
    echo -e "${GREEN}  ✅ Checked ${#MODIFIED_PHP[@]} modified/untracked PHP file(s).${NC}"
else
    echo -e "${GREEN}  ✅ No dirty PHP files detected (clean working tree).${NC}"
fi

if [ "$SYNTAX_ERRORS" -gt 0 ]; then
    FAILURES=$((FAILURES + 1))
fi

# 2. Targeted PHPStan Level 8 on modified src/app/tests files
echo -e "${BOLD}${CYAN}[FINISHER 2/4] Targeted PHPStan Level 8 Analysis...${NC}"
STAN_TARGETS=()
if [ "${#MODIFIED_PHP[@]}" -gt 0 ]; then
    for file in "${MODIFIED_PHP[@]}"; do
        if [[ "$file" =~ ^(src|app|controllers|models|services|middleware)/ ]] && [[ ! "$file" =~ \.blade\.php$ ]]; then
            STAN_TARGETS+=("$file")
        fi
    done
fi

if [ "${#STAN_TARGETS[@]}" -gt 0 ] && [ -x "vendor/bin/phpstan" ]; then
    if vendor/bin/phpstan analyse "${STAN_TARGETS[@]}" --level=8 --no-progress; then
        echo -e "${GREEN}  ✅ PHPStan Level 8 passed on ${#STAN_TARGETS[@]} modified source file(s).${NC}"
    else
        echo -e "${RED}  ❌ PHPStan Level 8 failed on modified source files.${NC}"
        FAILURES=$((FAILURES + 1))
    fi
else
    echo -e "${GREEN}  ✅ No modified core source files requiring PHPStan (or phpstan skipped).${NC}"
fi

# 3. Engineering Must-Haves Scanner
echo -e "${BOLD}${CYAN}[FINISHER 3/4] Running verify_engineering_must_haves.php...${NC}"
if [ -f "scripts/verify_engineering_must_haves.php" ]; then
    if php scripts/verify_engineering_must_haves.php; then
        echo -e "${GREEN}  ✅ Engineering Must-Haves passed (0 violations).${NC}"
    else
        echo -e "${RED}  ❌ Engineering Must-Haves failed!${NC}"
        FAILURES=$((FAILURES + 1))
    fi
fi

# 4. Targeted PHPUnit Tests (if passed via CLI arguments)
echo -e "${BOLD}${CYAN}[FINISHER 4/4] Targeted Feature/Regression Tests...${NC}"
if [ "$#" -gt 0 ] && [ -x "vendor/bin/phpunit" ]; then
    if vendor/bin/phpunit "$@"; then
        echo -e "${GREEN}  ✅ Targeted PHPUnit tests passed.${NC}"
    else
        echo -e "${RED}  ❌ Targeted PHPUnit tests failed!${NC}"
        FAILURES=$((FAILURES + 1))
    fi
else
    echo -e "${GREEN}  ✅ No extra test paths passed (ready for browser DOM audit).${NC}"
fi

echo -e "${BOLD}${BLUE}================================================================${NC}"
if [ "$FAILURES" -eq 0 ]; then
    echo -e "${BOLD}${GREEN}  ✅ AUTONOMOUS FINISHER GATE PASSED (Exit Code: 0)${NC}"
    exit 0
else
    echo -e "${BOLD}${RED}  ❌ AUTONOMOUS FINISHER GATE BLOCKED (${FAILURES} failure(s)) — SELF-HEAL REQUIRED${NC}"
    exit 1
fi
