/**
 * InteractiveAmortisationSlider.js
 *
 * Sovereign Real-Time Amortisation & Compound Interest Engine
 * engineered by the Advanced Technology Group (ATG) under Olutobi (Deloitte)
 * and Victor (CTO/BRATS) for LoanEasyFinance.
 *
 * Features:
 * - 60fps dynamic compound amortisation calculations.
 * - Real-time principal vs interest paydown distribution.
 * - Interactive early settlement & overpayment savings simulator.
 * - "Verified Solvency Passport" rate discount simulator.
 * - Zero third-party dependencies (pure Vanilla JS).
 */
class InteractiveAmortisationSlider {
    /**
     * @param {Object} options
     * @param {number} [options.principal=1500] Initial loan principal (£)
     * @param {number} [options.termMonths=6] Initial loan term in months
     * @param {number} [options.annualRate=0.24] Nominal annual interest rate (e.g. 0.24 = 24%)
     * @param {number} [options.extraMonthly=0] Monthly overpayment (£)
     * @param {boolean} [options.hasSolvencyPassport=false] Whether Solvency Passport is active
     * @param {number} [options.solvencyDiscountPercent=0.50] Rate discount for Prime Solvency (50%)
     * @param {Function} [options.onUpdate=null] Callback fired on any state change
     */
    constructor(options = {}) {
        this.principal = Number(options.principal) || 1500;
        this.termMonths = Number(options.termMonths) || 6;
        this.annualRate = Number(options.annualRate) || 0.24;
        this.extraMonthly = Number(options.extraMonthly) || 0;
        this.hasSolvencyPassport = Boolean(options.hasSolvencyPassport);
        this.solvencyDiscountPercent = Number(options.solvencyDiscountPercent) || 0.50;
        this.onUpdate = typeof options.onUpdate === 'function' ? options.onUpdate : null;

        this.cachedCalculation = null;
        this.recalculate();
    }

    /**
     * Updates loan parameters and recalculates amortisation.
     * @param {Object} params
     */
    update(params = {}) {
        if (params.principal !== undefined) this.principal = Math.max(100, Number(params.principal));
        if (params.termMonths !== undefined) this.termMonths = Math.max(1, Math.min(360, Number(params.termMonths)));
        if (params.annualRate !== undefined) this.annualRate = Math.max(0.01, Number(params.annualRate));
        if (params.extraMonthly !== undefined) this.extraMonthly = Math.max(0, Number(params.extraMonthly));
        if (params.hasSolvencyPassport !== undefined) this.hasSolvencyPassport = Boolean(params.hasSolvencyPassport);

        return this.recalculate();
    }

    /**
     * Toggles the Solvency Passport discount status.
     * @param {boolean} [forceState]
     */
    toggleSolvencyPassport(forceState) {
        this.hasSolvencyPassport = forceState !== undefined ? Boolean(forceState) : !this.hasSolvencyPassport;
        return this.recalculate();
    }

    /**
     * Executes the mathematical compound amortisation calculations.
     */
    recalculate() {
        const effectiveAnnualRate = this.hasSolvencyPassport
            ? Math.max(0.05, this.annualRate * (1.0 - this.solvencyDiscountPercent))
            : this.annualRate;

        const monthlyRate = effectiveAnnualRate / 12.0;

        // Base monthly repayment formula: M = P * [ r*(1+r)^n / ((1+r)^n - 1) ]
        let baseMonthlyRepayment = 0;
        if (monthlyRate > 0) {
            const factor = Math.pow(1.0 + monthlyRate, this.termMonths);
            baseMonthlyRepayment = Math.round((this.principal * (monthlyRate * factor) / (factor - 1.0)) * 100) / 100;
        } else {
            baseMonthlyRepayment = Math.round((this.principal / this.termMonths) * 100) / 100;
        }

        // Baseline schedule without extra repayment
        const baselineSchedule = this.generateRows(this.principal, this.termMonths, monthlyRate, baseMonthlyRepayment, 0);
        const baselineTotalInterest = baselineSchedule.reduce((sum, r) => sum + r.interestPortion, 0);

        // Actual schedule factoring in early overpayment
        const actualSchedule = this.generateRows(this.principal, this.termMonths, monthlyRate, baseMonthlyRepayment, this.extraMonthly);
        const actualTotalInterest = actualSchedule.reduce((sum, r) => sum + r.interestPortion, 0);
        const actualTotalRepayable = actualSchedule.reduce((sum, r) => sum + r.repayment, 0);
        const effectiveTermMonths = actualSchedule.length;

        const interestSaved = Math.max(0, Math.round((baselineTotalInterest - actualTotalInterest) * 100) / 100);
        const monthsSaved = Math.max(0, this.termMonths - effectiveTermMonths);

        // Calculate Solvency Passport Savings vs Standard baseline
        let passportInterestSaved = 0;
        if (this.hasSolvencyPassport) {
            const standardMonthlyRate = this.annualRate / 12.0;
            const stdFactor = Math.pow(1.0 + standardMonthlyRate, this.termMonths);
            const stdPayment = Math.round((this.principal * (standardMonthlyRate * stdFactor) / (stdFactor - 1.0)) * 100) / 100;
            const stdSchedule = this.generateRows(this.principal, this.termMonths, standardMonthlyRate, stdPayment, 0);
            const stdTotalInterest = stdSchedule.reduce((sum, r) => sum + r.interestPortion, 0);
            passportInterestSaved = Math.max(0, Math.round((stdTotalInterest - actualTotalInterest) * 100) / 100);
        }

        this.cachedCalculation = {
            principal: Math.round(this.principal * 100) / 100,
            termMonths: this.termMonths,
            standardAnnualRate: this.annualRate,
            effectiveAnnualRate: Math.round(effectiveAnnualRate * 10000) / 10000,
            effectiveAprPercent: Math.round(effectiveAnnualRate * 1000) / 10,
            monthlyRepayment: baseMonthlyRepayment,
            totalMonthlyRepayment: Math.round((baseMonthlyRepayment + this.extraMonthly) * 100) / 100,
            totalRepayable: Math.round(actualTotalRepayable * 100) / 100,
            totalInterest: Math.round(actualTotalInterest * 100) / 100,
            effectiveTermMonths,
            extraMonthly: this.extraMonthly,
            interestSaved,
            monthsSaved,
            hasSolvencyPassport: this.hasSolvencyPassport,
            passportInterestSaved,
            schedule: actualSchedule,
        };

        if (this.onUpdate) {
            this.onUpdate(this.cachedCalculation);
        }

        return this.cachedCalculation;
    }

    /**
     * @private
     */
    generateRows(principal, termMonths, monthlyRate, baseRepayment, extraRepayment) {
        const rows = [];
        let balance = principal;
        const totalMonthlyTarget = baseRepayment + extraRepayment;

        for (let m = 1; m <= termMonths; m++) {
            if (balance <= 0.001) break;

            const startingBalance = Math.round(balance * 100) / 100;
            const interestCharge = Math.round(startingBalance * monthlyRate * 100) / 100;
            const repayment = Math.min(startingBalance + interestCharge, totalMonthlyTarget);
            const principalPortion = Math.max(0, Math.round((repayment - interestCharge) * 100) / 100);
            const endingBalance = Math.max(0, Math.round((startingBalance - principalPortion) * 100) / 100);

            balance = endingBalance;

            rows.push({
                month: m,
                startingBalance,
                repayment: Math.round(repayment * 100) / 100,
                principalPortion,
                interestPortion: interestCharge,
                endingBalance,
            });
        }

        return rows;
    }

    /**
     * Renders a responsive visual paydown bar into an HTML container.
     * @param {HTMLElement} containerElement
     */
    renderPaydownBar(containerElement) {
        if (!containerElement || !this.cachedCalculation) return;

        const calc = this.cachedCalculation;
        const total = calc.totalRepayable || 1;
        const principalPct = Math.round((calc.principal / total) * 100);
        const interestPct = Math.max(1, 100 - principalPct);

        containerElement.innerHTML = `
            <div style="margin: 1rem 0;">
                <div style="display:flex; justify-content:space-between; font-size:0.75rem; font-weight:700; text-transform:uppercase; margin-bottom:0.35rem; color:#4B5563;">
                    <span>Principal (£${calc.principal.toLocaleString()}) - ${principalPct}%</span>
                    <span style="color:#DC2626;">Interest (£${calc.totalInterest.toLocaleString()}) - ${interestPct}%</span>
                </div>
                <div style="height:12px; border-radius:999px; background:#E5E7EB; overflow:hidden; display:flex;">
                    <div style="width:${principalPct}%; background:linear-gradient(90deg, #10B981, #059669); transition:width 0.3s ease;"></div>
                    <div style="width:${interestPct}%; background:linear-gradient(90deg, #F59E0B, #DC2626); transition:width 0.3s ease;"></div>
                </div>
            </div>
        `;
    }
}

// UMD / ESM Export
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { InteractiveAmortisationSlider };
}
if (typeof window !== 'undefined') {
    window.InteractiveAmortisationSlider = InteractiveAmortisationSlider;
}
