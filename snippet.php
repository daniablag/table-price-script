add_action('wp_footer', function () {
    if (!is_product()) return;

    global $product;

    if (!is_object($product) || !class_exists('\TierPricingTable\PriceManager')) return;

    $rule = \TierPricingTable\PriceManager::getPricingRule($product->get_id());
    $rules = $rule ? $rule->getRules() : [];

    if (!empty($rules)) {
        $min = min(array_values($rules));
        $max = 0;

        if ($product->is_type('variable')) {
            foreach ($product->get_children() as $variation_id) {
                $variation = wc_get_product($variation_id);
                if (!$variation) continue;
                $price = (float) $variation->get_regular_price();
                if ($price > $max) $max = $price;
            }
        } else {
            $max = (float) $product->get_regular_price();
        }

        if ($max > 0) {
            echo "<script>window.tiered_price_range = {min: {$min}, max: {$max}};</script>";
        }
    }
    ?>
    <script>
    (function(){
        'use strict';

        const wrapper = document.querySelector('.entry-summary');
        if (!wrapper) return;

        // --- УТИЛИТЫ ---
        function getRate() {
            const el = document.querySelector('.woocs_auto_switcher_link.woocs_curr_curr');
            if (!el) return 1;
            const m = el.innerText.match(/1\s*долл\.*\s*=\s*([\d.,]+)/i);
            return m ? parseFloat(m[1].replace(',', '.')) : 1;
        }

        function getCurr() {
            return (window.woocs_current_currency?.name || 'UAH').toUpperCase();
        }

        function getSymbol(curr) {
            const raw = window.woocs_currency_data?.[curr]?.symbol;
            if (raw) {
                const t = document.createElement('textarea');
                t.innerHTML = raw;
                return t.value;
            }
            return curr === 'USD' ? '$' : 'грн.';
        }

        function fmt(n) {
            return n.toLocaleString('ru-RU', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function getBasePriceForQty(table, qty) {
            let rules = {};
            try { rules = JSON.parse(table.dataset.priceRules); } catch {}
            let base = parseFloat(table.dataset.regularPrice.replace(',', '.'));
            Object.keys(rules).map(n => +n).sort((a, b) => a - b)
            .forEach(th => { if (qty >= th) base = parseFloat(rules[th]); });
            return base;
        }

        // --- ЛОГИКА TIERED PRICING TABLE ---
        function recalcTiered(scope) {
            const rate = getRate(), curr = getCurr(), symbol = getSymbol(curr);
            scope.querySelectorAll('table.tiered-pricing-table[data-price-rules]').forEach(table => {
                const regular = parseFloat(table.dataset.regularPrice.replace(',', '.'));
                let rules = {};
                try { rules = JSON.parse(table.dataset.priceRules); } catch {}
                table.querySelectorAll('tbody tr').forEach(row => {
                    const qty = row.dataset.tieredQuantity;
                    let base = rules.hasOwnProperty(qty) ? parseFloat(rules[qty]) : regular;
                    if (curr === 'UAH') base *= rate;
                    const cell = row.querySelector('.woocommerce-Price-amount');
                    if (cell) {
                        cell.innerHTML = `${fmt(base)}&nbsp;<span class="woocommerce-Price-currencySymbol">${symbol}</span>`;
                    }
                });
            });
        }

        function recalcVariation(scope) {
            const rate = getRate(), curr = getCurr(), symbol = getSymbol(curr);
            scope.querySelectorAll(
                '.woocommerce-variation-price .woocommerce-Price-amount.amount,'+
                '.woocommerce-variation .woocommerce-Price-amount.amount'
            ).forEach(el => {
                if (el.closest('.tiered-pricing-dynamic-price-wrapper')) return;
                const table = scope.querySelector('table.tiered-pricing-table');
                if (table) {
                    const qty = parseInt(scope.querySelector('.variations_form input.qty')?.value || 1, 10);
                    const base = getBasePriceForQty(table, qty);
                    const display = curr === 'UAH' ? base * rate : base;
                    el.innerHTML = `${fmt(display)}&nbsp;<span class="woocommerce-Price-currencySymbol">${symbol}</span>`;
                }
            });
        }

        function recalcStrikethrough(scope) {
            const rate = getRate(), curr = getCurr(), symbol = getSymbol(curr);
            scope.querySelectorAll('del.woocommerce-Price-amount.amount').forEach(el => {
                const table = scope.querySelector('table.tiered-pricing-table');
                if (!table) return;
                let base = parseFloat(table.dataset.regularPrice.replace(',', '.'));
                if (curr === 'UAH') base *= rate;
                el.innerHTML = `${fmt(base)}&nbsp;<span class="woocommerce-Price-currencySymbol">${symbol}</span>`;
            });
        }

        function recalcDynamic(scope) {
            const rate = getRate(), curr = getCurr(), symbol = getSymbol(curr);
            const qty = parseInt(scope.querySelector('.variations_form input.qty')?.value || 1, 10);
            const table = scope.querySelector('table.tiered-pricing-table[data-price-rules]');
            if (!table) return;
            const base = getBasePriceForQty(table, qty);
            const total = curr === 'UAH' ? base * rate * qty : base * qty;
            scope.querySelectorAll('.tiered-pricing-dynamic-price-wrapper .woocommerce-Price-amount.amount').forEach(el => {
                el.innerHTML = `${fmt(total)}&nbsp;<span class="woocommerce-Price-currencySymbol">${symbol}</span>`;
            });
        }

        function recalcSimpleProductPrice(scope) {
            const el = scope.querySelector('.product .price .woocommerce-Price-amount.amount');
            const table = scope.querySelector('table.tiered-pricing-table[data-price-rules]');
            if (!el || !table) return;
            const rate = getRate(), curr = getCurr(), symbol = getSymbol(curr);
            const qty = parseInt(scope.querySelector('.cart input.qty')?.value || 1, 10);
            const base = getBasePriceForQty(table, qty);
            const display = curr === 'UAH' ? base * rate : base;
            el.innerHTML = `${fmt(display)}&nbsp;<span class="woocommerce-Price-currencySymbol">${symbol}</span>`;
        }

        function recalcTieredDynamicBlock(scope) {
            const rate = getRate(), curr = getCurr(), symbol = getSymbol(curr);
            const qty = parseInt(scope.querySelector('.qty')?.value || 1, 10);
            const table = scope.querySelector('table.tiered-pricing-table[data-price-rules]');
            if (!table) return;

            const base = getBasePriceForQty(table, qty);
            const regular = parseFloat(table.dataset.regularPrice.replace(',', '.'));
            const regularDisplay = curr === 'UAH' ? regular * rate * qty : regular * qty;
            const discountedDisplay = curr === 'UAH' ? base * rate * qty : base * qty;

            scope.querySelectorAll('.tiered-pricing-dynamic-price-wrapper').forEach(wrapper => {
                const del = wrapper.querySelector('del .woocommerce-Price-amount.amount');
                const ins = wrapper.querySelector('ins .woocommerce-Price-amount.amount');
                const solo = wrapper.querySelector('.woocommerce-Price-amount.amount');

                if (del) del.innerHTML = `${fmt(regularDisplay)}&nbsp;<span class="woocommerce-Price-currencySymbol">${symbol}</span>`;
                if (ins) ins.innerHTML = `${fmt(discountedDisplay)}&nbsp;<span class="woocommerce-Price-currencySymbol">${symbol}</span>`;
                if (!del && !ins && solo) solo.innerHTML = `${fmt(discountedDisplay)}&nbsp;<span class="woocommerce-Price-currencySymbol">${symbol}</span>`;

                if (del && ins && del.textContent === ins.textContent) {
                    del.parentElement.style.display = 'none';
                }
            });
        }

        function recalcTieredSummary(scope) {
            const rate = getRate(), curr = getCurr(), symbol = getSymbol(curr);
            const qty = parseInt(scope.querySelector('[data-tier-pricing-table-summary-product-qty]')?.textContent || 1, 10);
            const table = scope.querySelector('table.tiered-pricing-table[data-price-rules]');
            if (!table) return;

            const base = getBasePriceForQty(table, qty);
            const regular = parseFloat(table.dataset.regularPrice.replace(',', '.'));
            const displayUnit = curr === 'UAH' ? base * rate : base;
            const displayTotal = displayUnit * qty;
            const displayRegularUnit = curr === 'UAH' ? regular * rate : regular;

            const unitEl = scope.querySelector('[data-tier-pricing-table-summary-product-price] .woocommerce-Price-amount.amount');
            const totalEl = scope.querySelector('[data-tier-pricing-table-summary-total-with-tax] .woocommerce-Price-amount.amount');
            const oldUnitEl = scope.querySelector('[data-tier-pricing-table-summary-product-old-price]');

            if (unitEl) unitEl.innerHTML = `${fmt(displayUnit)}&nbsp;<span class="woocommerce-Price-currencySymbol">${symbol}</span>`;
            if (totalEl) totalEl.innerHTML = `${fmt(displayTotal)}&nbsp;<span class="woocommerce-Price-currencySymbol">${symbol}</span>`;
            if (oldUnitEl) {
                oldUnitEl.innerHTML = `${fmt(displayRegularUnit)}&nbsp;<span class="woocommerce-Price-currencySymbol">${symbol}</span>`;
                const oldVal = parseFloat(oldUnitEl.textContent.replace(/[^\d.,]/g, '').replace(',', '.'));
                const unitVal = parseFloat(unitEl.textContent.replace(/[^\d.,]/g, '').replace(',', '.'));
                if (Math.abs(oldVal - unitVal) < 0.01) oldUnitEl.parentElement.style.display = 'none';
                else oldUnitEl.parentElement.style.display = '';
            }
        }

        // --- БЛОК ДЛЯ ТОВАРОВ БЕЗ СКИДОК ---
        function renderNoTierSummary(scope) {
            if (window.tiered_price_range) return; // Только если нет скидок
            if (scope.querySelector('.no-tiers-summary-table')) return;

            const qty = parseInt(scope.querySelector('.cart input.qty, .variations_form input.qty')?.value || 1, 10);
            let price = 0, name = '';
            const curr = getCurr(), rate = getRate(), symbol = getSymbol(curr);

            // ФАКТИЧЕСКАЯ цена (учитываем ins)
            let priceEl = scope.querySelector('.product .price ins .woocommerce-Price-amount.amount bdi');
            if (priceEl) {
                price = parseFloat(priceEl.textContent.replace(/[^\d.,]/g, '').replace(',', '.'));
            } else {
                priceEl = scope.querySelector('.product .price .woocommerce-Price-amount.amount bdi');
                if (priceEl) price = parseFloat(priceEl.textContent.replace(/[^\d.,]/g, '').replace(',', '.'));
            }

            const nameEl = scope.querySelector('.product_title, .entry-title');
            if (nameEl) name = nameEl.textContent.trim();

            const total = price * qty;

            const html = `
<div class="no-tiers-summary-table" data-product-id="">
  <h4 style="margin: 10px 0;">ВСЬОГО:</h4>
  <div class="tiered-pricing-totals tiered-pricing-totals--no-tiers">
    <div style="display: flex; justify-content: space-between; border-top: 1px dashed #f5f5f5; border-bottom: 1px dashed #f5f5f5; padding: 5px 0;">
      <div>
        <span class="no-tiers-summary-qty">${qty}</span>
        <span style="font-size: .9em;">×</span>
        <span class="no-tiers-summary-name">${name}</span>
      </div>
      <div>
        <span style="font-size: 1.15em" class="no-tiers-summary-price">
          <span class="woocommerce-Price-amount amount">${fmt(price)}&nbsp;<span class="woocommerce-Price-currencySymbol">${symbol}</span></span>
        </span>
      </div>
    </div>
    <div style="display: flex; justify-content: space-between; font-size: 1.3em; margin-top:5px">
      <div>Загальна Сума</div>
      <div>
        <span class="no-tiers-summary-total">
          <span class="woocommerce-Price-amount amount">${fmt(total)}&nbsp;<span class="woocommerce-Price-currencySymbol">${symbol}</span></span>
        </span>
      </div>
    </div>
  </div>
</div>
`;
            const afterBlock = scope.querySelector('.cart, .variations_form');
            if (afterBlock) {
                afterBlock.insertAdjacentHTML('afterend', html);
            } else {
                scope.insertAdjacentHTML('beforeend', html);
            }
        }

        function updateNoTierSummary(scope) {
            if (window.tiered_price_range) return;
            const table = scope.querySelector('.no-tiers-summary-table');
            if (!table) return;
            const qty = parseInt(scope.querySelector('.cart input.qty, .variations_form input.qty')?.value || 1, 10);
            let price = 0;
            const curr = getCurr(), rate = getRate(), symbol = getSymbol(curr);

            // ФАКТИЧЕСКАЯ цена (учитываем ins)
            let priceEl = scope.querySelector('.product .price ins .woocommerce-Price-amount.amount bdi');
            if (priceEl) {
                price = parseFloat(priceEl.textContent.replace(/[^\d.,]/g, '').replace(',', '.'));
            } else {
                priceEl = scope.querySelector('.product .price .woocommerce-Price-amount.amount bdi');
                if (priceEl) price = parseFloat(priceEl.textContent.replace(/[^\d.,]/g, '').replace(',', '.'));
            }

            const total = price * qty;

            table.querySelector('.no-tiers-summary-qty').textContent = qty;
            table.querySelector('.no-tiers-summary-price .woocommerce-Price-amount.amount').innerHTML = `${fmt(price)}&nbsp;<span class="woocommerce-Price-currencySymbol">${symbol}</span>`;
            table.querySelector('.no-tiers-summary-total .woocommerce-Price-amount.amount').innerHTML = `${fmt(total)}&nbsp;<span class="woocommerce-Price-currencySymbol">${symbol}</span>`;
        }

        // --- ВЫЗОВЫ ---
        function runAll() {
            recalcTiered(wrapper);
            recalcVariation(wrapper);
            recalcStrikethrough(wrapper);
            recalcDynamic(wrapper);
            recalcSimpleProductPrice(wrapper);
            recalcTieredDynamicBlock(wrapper);
            recalcTieredSummary(wrapper);

            // Новое: если нет скидочных правил — рисуем summary
            if (!window.tiered_price_range) {
                renderNoTierSummary(wrapper);
                updateNoTierSummary(wrapper);
            } else {
                // Если были скидки, а теперь их нет — удалить старую таблицу
                const old = wrapper.querySelector('.no-tiers-summary-table');
                if (old) old.remove();
            }

            // Диапазон цен до выбора вариации
            (function insertInitialRange() {
                if (!window.tiered_price_range) return;
                if (wrapper.querySelector('.woocommerce-variation-price')) return;

                const priceBlock = wrapper.querySelector('.price');
                const amount = priceBlock?.querySelector('.woocommerce-Price-amount.amount');
                if (!priceBlock || !amount || priceBlock.querySelector('.price-range-inserted')) return;

                const rate = getRate();
                const symbol = getSymbol(getCurr());
                const min = fmt(window.tiered_price_range.min * rate);
                const max = fmt(window.tiered_price_range.max * rate);

                amount.innerHTML = `${min} – ${max}&nbsp;<span class="woocommerce-Price-currencySymbol">${symbol}</span>`;
                amount.classList.add('price-range-inserted');
            })();
        }

        window.addEventListener('load', runAll);
        window.addEventListener('popstate', runAll);
        setTimeout(runAll, 600);

        if (window.jQuery) {
            const $ = jQuery;
            $(document).ajaxComplete(() => setTimeout(runAll, 50));
            $(document.body).on(
                'found_variation show_variation woocommerce_variation_has_changed change input',
                '.variations_form input.qty, .cart input.qty',
                () => setTimeout(runAll, 50)
            );
            $(document.body).on('woocs_current_currency_changed', () => setTimeout(runAll, 100));
        }
    })();
    </script>
    <?php
});
