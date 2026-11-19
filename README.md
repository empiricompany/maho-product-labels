# Product Labels for Maho

Rule-driven product labels (badges) for category listings, product pages, widgets and
carousels. One rule defines the condition and the look; the label then appears the same
everywhere.

## How it works

- **Rules** use the framework rule engine and the catalog price rule condition widget, so
  the admin screen is the one you already know. Website and customer group selectors behave
  like the catalog rules; there is no per store view visibility.
- **Text and image are per store view** (store view `0` is the default, other store views
  fall back to it). Position and colors belong to the label and are shared.
- **An index table** (`productlabels_index`) holds the association rule → product →
  website → customer group. The storefront runs **one** query per page, whatever the number
  of rules; nothing is evaluated per product.
- **Values are computed at render time from data the product already carries**
  (`price`, `final_price`, stock quantity, attributes), so pages stay cacheable. Dates can
  also be relative to today (`is on or after today`, `is on or before today` with a day
  offset): those are resolved by the nightly index rebuild, never per request.

## Install

```sh
composer require empiricompany/maho-product-labels
./maho migrate
```

Enable the module in *Stores → Configuration → Catalog → Product Labels*.

## Use it in a template

```php
<?= $this->helper('productlabels')->render($_product) ?>
```

Place it inside an element with `position: relative` (the module ships the
`pl-labels-host` class for that). For the product page:

```php
<?= $this->helper('productlabels')->render(Mage::registry('current_product')) ?>
```

For custom code, `Mage::helper('productlabels')->getLabels($product)` returns the resolved
label data.

## Placeholders

| Placeholder | Value |
| --- | --- |
| `{discount_percent}` | discount percentage between `price` and the real final price |
| `{discount_amount}` | discount amount, formatted with the store currency |
| `{price}` / `{final_price}` | formatted prices |
| `{qty}` | stock quantity |
| `{attribute:code}` | product attribute value, printed by attribute type (see below) |

A placeholder that cannot be computed for a product renders empty, and a text that ends up
empty removes the label instead of printing an empty badge.

`{attribute:code}` follows the attribute type:

| Attribute type | Printed |
| --- | --- |
| `select` | the option label (`Cotton`) |
| `multiselect` | the option labels joined with `, ` (`Cotton, Linen`) |
| `boolean` | the option label, translated (`Yes` / `No`) |
| `price` | the value formatted with the store currency (`€28.00`) |
| `text`, `textarea` | the value with tags stripped and whitespace collapsed |
| missing or empty | nothing |

The value is read from the store the rule is evaluated on (see the note below), not from the
store view the customer is browsing.

## Use cases

Conditions are the catalog price rule ones, with two extra operators for date attributes:
`is on or after today` and `is on or before today`, whose value is a day offset
(`0` = today, `-7` = a week ago, `30` = in a month). A date attribute with a normal operator
(`is`, `equals or greater than`, `equals or less than`) takes a fixed date from the calendar.

| Case | Conditions | Label text | Look |
| --- | --- | --- | --- |
| New arrivals | `Set Product as New from Date` **is on or before today** `0` **AND** `Set Product as New to Date` **is on or after today** `0` | `NEW` | top left, white on blue |
| Discount badge | `Special Price` **greater than** `0` | `-{discount_percent}%` | top right, white on red |
| Free shipping | `Price` **equals or greater than** `50` | `Free shipping` | bottom right, white on green |
| Promotion ending soon | `Special Price To Date` **is on or after today** `0` **AND** `Special Price To Date` **is on or before today** `3` | `Last days` | top left, white on orange |
| Coming soon | `Set Product as New from Date` **is on or after today** `7` | `Coming soon` | top left, white on indigo |
| Attribute value as text | `Material` **is** `Cotton` | `{attribute:material}` | top right, white on black |
| Brand badge | `Brand` **is** `Acme` (any brand you sell) | `{attribute:brand}` | bottom right, white on slate |
| B2B price | `Price` **equals or greater than** `500`, customer groups = *Wholesale* | `B2B price` | bottom left, white on navy |

Notes:

- Rules are evaluated by the indexer against the **default store of the first website** of the
  rule, so store-scoped attributes (dates included) are read from that store's values; leave
  the values at *All Store Views* when you want one rule for every website.
- Website and customer group selectors work like the catalog rules; empty means "all". Two
  rules on the same corner are ordered by priority (lower first) and *Stop Further Labels*.
- Text and image are per store view with fallback to the default one, so `NEW` can be
  `NOVITÀ` on the Italian store view without duplicating the rule.
- An **image replaces the text and the colors**: position still applies, but the label is
  drawn from the image only, with no background and no padding around it.
- **Stock quantity is not a condition** (it lives in the stock tables, not in the product
  attributes), but `{qty}` is available as a text placeholder: a rule such as
  `Material is Cotton` with the text `Only {qty} left` prints the quantity at the last rebuild.

### Example: a new-arrivals label that maintains itself

1. Create a rule, name it `New arrivals`, position `Top Left`, text color `#ffffff`,
   background `#0ea5e9`.
2. Conditions: `Set Product as New from Date is on or before today` with `0` days, add a
   second condition `Set Product as New to Date is on or after today` with `0` days, both in
   the same *ALL* group.
3. Label tab: `NEW` on the default store view, `NOVITÀ` on the Italian one; leave the image
   empty to render the text.
4. Nothing else: the nightly rebuild adds the label when a product enters its new window and
   removes it when the window closes.

### Example: print the value of a product attribute

1. Rule `Material badge`, position `Top Right`, background `#111827`.
2. Conditions: `Material is Cotton` (or drop the condition to label every product).
3. Text: `{attribute:material}`.

The placeholder is formatted by the attribute frontend, so a select attribute prints its
option label (`Cotton`), not the stored id; a multiselect prints every label, a boolean
prints `Yes`/`No`, a price attribute prints the value with the store currency.

## Index

```sh
./maho productlabels:reindex              # all rules
./maho productlabels:reindex --rule=5     # one rule
./maho productlabels:reindex --product=42 # one product
```

A nightly cron rebuilds the index (prices, stock and dates change without a product save).
Saving a rule or a product reindexes only what changed.

## Theme integration

The module ships `css/productlabels.css` and the `pl-label` markup. The rule reproduces the badge
of the base theme (24px height, 11px side padding, 8px radius, 1px border, 12px bold uppercase
text, the same shadow as the discount badge), so a label looks like the other badges of the
storefront on any theme and the core stays untouched. The markup also carries the `badge` class,
so a theme can style the component and adjust `.pl-label` on top of it.

The rule colors are mapped onto the badge variables (`--badge-bg`, `--badge-fg`,
`--badge-color`) from `--pl-bg` and `--pl-fg`, with the theme tokens as fallback
(`--color-error`, `--color-error-content`) and then fixed values.

The template that renders the products must call the helper inside the image link:

```php
<a href="..." class="product-image">
    <img ...>
    <?= $this->helper('productlabels')->render($_product) ?>
</a>
```

A label with position `content` does not belong to the image overlay: it goes in the text block of
the card, through a second call. Add it after the price, in both view modes:

```php
<?= $this->helper('productlabels')->renderContent($_product) ?>
```

`render()` prints every position but `content`; `renderContent()` prints only `content`, inside a
flex wrapper so several labels sit on one line.

The module styles the base theme list too, but it cannot add those lines: a theme that does not
call the helper has no labels and no error.
