const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const storePath = path.resolve(__dirname, '../../resources/js/front/cart/store.js');

function createCartHarness({ couponResult = 1, couponError = null, refreshedCart } = {}) {
    const initialCart = {
        count: 2,
        items: [{ id: 17, quantity: 2 }],
        subtotal: 100,
        total: 100,
        coupon: 'EXISTING',
        detail_con: [],
    };
    const nextCart = refreshedCart || {
        ...initialCart,
        total: 80,
        coupon: 'SAVE20',
        detail_con: [{ name: 'Newsletter', value: '-20' }],
    };
    const requests = [];
    const notices = [];
    const dispatches = [];
    const storedValues = new Map([['sl_cart', JSON.stringify(initialCart)]]);

    const sandbox = {
        axios: {
            async get(url) {
                requests.push(url);

                if (url === 'cart/get') {
                    return { data: nextCart };
                }

                assert.ok(url.startsWith('cart/coupon/'), `Unexpected request: ${url}`);
                if (couponError) {
                    throw couponError;
                }

                return { data: couponResult };
            },
        },
        localStorage: {
            getItem(key) {
                return storedValues.get(key) ?? null;
            },
            setItem(key, value) {
                storedValues.set(key, String(value));
            },
        },
        window: {
            ToastWarning: { fire: message => notices.push({ type: 'warning', message }) },
            ToastSuccess: { fire: message => notices.push({ type: 'success', message }) },
        },
    };

    // Evaluate the real application store without requiring a browser or bundler.
    const source = fs.readFileSync(storePath, 'utf8')
        .replace(/export\s+default\s+store\s*;?\s*$/, 'globalThis.couponStore = store;');
    assert.notEqual(source, fs.readFileSync(storePath, 'utf8'), 'Store export must be exposed to the harness');
    vm.runInNewContext(source, sandbox, { filename: storePath });

    const store = sandbox.couponStore;
    store.state.cart = initialCart;
    const context = {
        state: store.state,
        commit(name, payload) {
            assert.equal(name, 'setCart');
            store.mutations[name](store.state, payload);
        },
        dispatch(name, payload) {
            dispatches.push(name);
            return store.actions[name](context, payload);
        },
    };

    return {
        store,
        context,
        initialCart,
        nextCart,
        requests,
        notices,
        dispatches,
        storedCart: () => JSON.parse(storedValues.get('sl_cart')),
    };
}

test('coupon service trims and encodes the code without showing a notice', async () => {
    const harness = createCartHarness();

    const result = await harness.store.state.service.checkCoupon('  BOOK/20 +?#  ');

    assert.equal(result, 1);
    assert.deepEqual(harness.requests, ['cart/coupon/BOOK%2F20%20%2B%3F%23']);
    assert.deepEqual(harness.notices, []);
});

test('valid coupon refreshes and persists the server cart with one success notice', async () => {
    const harness = createCartHarness();

    const result = await harness.store.actions.checkCoupon(harness.context, '  SAVE20  ');

    assert.equal(result, true);
    assert.deepEqual(harness.requests, ['cart/coupon/SAVE20', 'cart/get']);
    assert.deepEqual(harness.dispatches, ['getCart']);
    assert.equal(harness.store.state.cart, harness.nextCart);
    assert.deepEqual(harness.storedCart(), harness.nextCart);
    assert.deepEqual(harness.notices, [{
        type: 'success',
        message: harness.store.state.messages.couponSuccess,
    }]);
});

test('invalid coupon retains the existing cart and shows only the invalid-code warning', async () => {
    const harness = createCartHarness({ couponResult: 0 });

    const result = await harness.store.actions.checkCoupon(harness.context, 'INVALID');

    assert.equal(result, false);
    assert.deepEqual(harness.requests, ['cart/coupon/INVALID']);
    assert.deepEqual(harness.dispatches, []);
    assert.equal(harness.store.state.cart, harness.initialCart);
    assert.deepEqual(harness.storedCart(), harness.initialCart);
    assert.deepEqual(harness.notices, [{
        type: 'warning',
        message: harness.store.state.messages.couponError,
    }]);
});

test('removing a coupon refreshes the undiscounted cart with one removal notice', async () => {
    const refreshedCart = {
        count: 2,
        items: [{ id: 17, quantity: 2 }],
        subtotal: 100,
        total: 100,
        coupon: null,
        detail_con: [],
    };
    const harness = createCartHarness({ refreshedCart });

    const result = await harness.store.actions.checkCoupon(harness.context, '   ');

    assert.equal(result, true);
    assert.deepEqual(harness.requests, ['cart/coupon/null', 'cart/get']);
    assert.deepEqual(harness.dispatches, ['getCart']);
    assert.equal(harness.store.state.cart, refreshedCart);
    assert.deepEqual(harness.storedCart(), refreshedCart);
    assert.ok(harness.store.state.messages.couponRemoved);
    assert.deepEqual(harness.notices, [{
        type: 'success',
        message: harness.store.state.messages.couponRemoved,
    }]);
});

test('failed request retains the cart and shows only the connection warning', async () => {
    const harness = createCartHarness({ couponError: new Error('Connection unavailable') });

    const result = await harness.store.actions.checkCoupon(harness.context, 'SAVE20');

    assert.equal(result, false);
    assert.deepEqual(harness.requests, ['cart/coupon/SAVE20']);
    assert.deepEqual(harness.dispatches, []);
    assert.equal(harness.store.state.cart, harness.initialCart);
    assert.deepEqual(harness.storedCart(), harness.initialCart);
    assert.deepEqual(harness.notices, [{
        type: 'warning',
        message: harness.store.state.messages.error,
    }]);
});
