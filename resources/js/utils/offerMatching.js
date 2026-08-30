/**
 * Client-side offer matching (mirrors OfferMatchingService).
 */

export function applyOffersToCart(offers, cartItems) {
  const sortedOffers = [...(offers || [])].sort((a, b) => (b.priority || 0) - (a.priority || 0));
  const units = expandCartUnits(cartItems);
  const applied = [];

  for (const offer of sortedOffers) {
    while (true) {
      const match = tryMatchOffer(offer, units);
      if (!match) break;
      match.usedKeys.forEach((key) => delete units[key]);
      applied.push(buildAppliedBundle(offer, match.units));
    }
  }

  return {
    applied,
    remaining: collapseUnitsToCartLines(units, cartItems),
  };
}

function expandCartUnits(cartItems) {
  const units = {};
  for (const item of cartItems || []) {
    if (item.type === 'offer') continue;
    const qty = item.quantity || 1;
    for (let i = 0; i < qty; i++) {
      const key = `${item.cartItemId}#${i}`;
      units[key] = {
        cartItemId: item.cartItemId,
        product_id: item.product_id,
        category_id: item.category_id,
        size: item.size ?? null,
        name: item.name,
        price: parseFloat(item.price) || 0,
        from_fridge: !!item.from_fridge,
      };
    }
  }
  return units;
}

function tryMatchOffer(offer, units) {
  const usedKeys = [];
  const matchedUnits = [];
  const rules = [...(offer.rules || [])].sort((a, b) => a.slot_index - b.slot_index);

  for (const rule of rules) {
    const picked = pickForRule(rule, units, usedKeys);
    if (!picked) return null;
    Object.entries(picked).forEach(([key, unit]) => {
      usedKeys.push(key);
      matchedUnits.push(unit);
    });
  }

  return { units: matchedUnits, usedKeys };
}

function pickForRule(rule, units, usedKeys) {
  const picked = {};
  const usedSet = new Set(usedKeys);

  if (rule.rule_type === 'fixed_products') {
    for (const ruleProduct of rule.products || []) {
      const need = ruleProduct.quantity || 1;
      for (let i = 0; i < need; i++) {
        const foundKey = Object.keys(units).find((key) => {
          if (usedSet.has(key)) return false;
          const unit = units[key];
          if (unit.product_id !== ruleProduct.product_id) return false;
          if (ruleProduct.size && unit.size !== ruleProduct.size) return false;
          return true;
        });
        if (!foundKey) return null;
        picked[foundKey] = units[foundKey];
        usedSet.add(foundKey);
      }
    }
    return picked;
  }

  if (rule.rule_type === 'category_pick') {
    const need = rule.quantity || 1;
    const categoryId = rule.category_id;
    for (let i = 0; i < need; i++) {
      const foundKey = Object.keys(units).find((key) => {
        if (usedSet.has(key)) return false;
        return units[key].category_id === categoryId;
      });
      if (!foundKey) return null;
      picked[foundKey] = units[foundKey];
      usedSet.add(foundKey);
    }
    return picked;
  }

  if (rule.rule_type === 'product_pick') {
    const need = rule.quantity || 1;
    const allowed = new Set((rule.products || []).map((p) => p.product_id));
    for (let i = 0; i < need; i++) {
      const foundKey = Object.keys(units).find((key) => {
        if (usedSet.has(key)) return false;
        return allowed.has(units[key].product_id);
      });
      if (!foundKey) return null;
      picked[foundKey] = units[foundKey];
      usedSet.add(foundKey);
    }
    return picked;
  }

  return null;
}

function collapseUnitsToCartLines(units, originalCart) {
  const counts = {};
  Object.values(units).forEach((unit) => {
    const id = unit.cartItemId;
    counts[id] = (counts[id] || 0) + 1;
  });

  const originalById = Object.fromEntries(
    (originalCart || []).map((item) => [item.cartItemId, item])
  );

  return Object.entries(counts)
    .map(([cartItemId, qty]) => {
      const base = originalById[cartItemId];
      if (!base) return null;
      return { ...base, quantity: qty };
    })
    .filter(Boolean);
}

function buildAppliedBundle(offer, units) {
  const grouped = {};
  units.forEach((unit) => {
    const key = `${unit.product_id}|${unit.size || ''}|${unit.from_fridge ? 1 : 0}`;
    if (!grouped[key]) {
      grouped[key] = {
        product_id: unit.product_id,
        product_name: unit.name,
        size: unit.size,
        quantity: 0,
        unit_price: unit.price,
        from_fridge: !!unit.from_fridge,
      };
    }
    grouped[key].quantity += 1;
  });

  const components = Object.values(grouped);
  const originalTotal = components.reduce((sum, c) => sum + c.unit_price * c.quantity, 0);

  return {
    cartItemId: `offer-${offer.id}-${crypto.randomUUID()}`,
    type: 'offer',
    offer_id: offer.id,
    name: `🎁 ${offer.name}`,
    price: parseFloat(offer.offer_price) || 0,
    quantity: 1,
    original_total: Math.round(originalTotal * 100) / 100,
    savings: Math.round(Math.max(0, originalTotal - (parseFloat(offer.offer_price) || 0)) * 100) / 100,
    components,
  };
}

export function describeOfferRules(offer) {
  return (offer.rules || [])
    .map((rule) => {
      if (rule.rule_type === 'fixed_products') {
        return (rule.products || [])
          .map((p) => `${p.quantity || 1}× منتج #${p.product_id}`)
          .join(' + ');
      }
      if (rule.rule_type === 'category_pick') {
        return `${rule.quantity} من الفئة #${rule.category_id}`;
      }
      if (rule.rule_type === 'product_pick') {
        return `${rule.quantity} من (${(rule.products || []).length} منتج)`;
      }
      return '';
    })
    .filter(Boolean)
    .join(' + ');
}
