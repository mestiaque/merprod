<?php

namespace ME\MerchandisingSfl\Support;

/**
 * "এই পাতা কী কাজে" — the help box shown on top of every screen (partials/page-help).
 *
 * Keyed by the route name without the `msfl.` prefix and the action, e.g. `orders`
 * for msfl.orders.index / create / show; masters by slug (`masters.buyers`),
 * production stages by stage (`production.entries.sewing`), reports by report key.
 * Each entry: title, what (purpose), steps, example (demo data, codes D-…), before / after (process flow).
 * Optional `actions` => [action => extra tip] for create / edit / show pages.
 */
class PageHelp
{
    /** Help for the current request, or null when the page has none. */
    public static function current(): ?array
    {
        $route = request()->route();
        if (! $route || ! $route->getName()) {
            return null;
        }

        $prefix = config('merchandising-sfl.route.as', 'msfl.');
        $name = str_starts_with($route->getName(), $prefix) ? substr($route->getName(), strlen($prefix)) : $route->getName();
        $action = str_contains($name, '.') ? substr($name, strrpos($name, '.') + 1) : $name;
        $module = str_contains($name, '.') ? substr($name, 0, strrpos($name, '.')) : $name;

        $key = match ($module) {
            'masters' => 'masters.' . $route->parameter('master'),
            'production.entries' => 'production.entries.' . $route->parameter('stage'),
            'reports' => $action === 'index' ? 'reports' : 'reports.' . $route->parameter('report'),
            default => $module,
        };

        $help = self::all()[$key] ?? null;
        if (! $help) {
            return null;
        }

        $help['key'] = $key;
        $help['tip'] = $help['actions'][$action] ?? null;

        return $help;
    }

    public static function all(): array
    {
        $stage = fn (string $title, string $what, string $before, string $after, string $example) => [
            'title' => $title,
            'what' => $what,
            'steps' => [
                '<strong>+ New Entry</strong> চাপুন, PO বেছে নিন — "can take in" দেখাবে আগের stage থেকে কত pcs নেওয়া যাবে।',
                'দিনের <strong>Input</strong> (এই stage-এ কত pcs ঢুকল) আর QC ফল দিন: <strong>Pass</strong>, <strong>Rework</strong>, <strong>Reject</strong>।',
                'Rework / Reject থাকলে defect row-এ ভাগ করে দিন (কোন part, defect, চাইলে কোন machine, কত pcs) — যোগফল মিলতে হবে।',
                'WIP = Input − Pass − Reject। Rework WIP-এই থাকে যতক্ষণ না pass হয়; reject flow থেকে বেরিয়ে যায়।',
            ],
            'example' => $example,
            'before' => $before,
            'after' => $after,
            'actions' => ['create' => 'Input কখনো "can take in"-এর বেশি হতে পারবে না, আর Pass + Reject এই stage-এ থাকা pcs-এর বেশি হতে পারবে না।'],
        ];

        return [
            // ---------------------------------------------------------------- Dashboard
            'dashboard' => [
                'title' => 'Dashboard',
                'what' => 'পুরো merchandising ও production-এর এক নজরের সারাংশ: চলমান order, আজকের production (stage অনুযায়ী), WIP, reject %, সামনে কোন shipment আছে, কোন T&A task overdue, আর সবচেয়ে বেশি কোন defect হচ্ছে।',
                'steps' => [
                    'এখানে কিছু entry দিতে হয় না — অন্য পাতায় যা entry দেন তা থেকেই সংখ্যাগুলো আসে।',
                    'লাল / overdue দেখলে সেই কার্ডের লিংকে গিয়ে কাজটা শেষ করুন।',
                ],
                'example' => 'ধরুন আজ Sewing-এ 200 pcs pass দেওয়া হলো — Dashboard-এর "Today\'s production"-এ Sewing-এর পাশে 200 দেখাবে।',
                'before' => 'সব পাতার entry',
                'after' => 'যে কাজ বাকি সেই পাতা',
            ],

            // ---------------------------------------------------------------- Master data
            'masters.buyers' => [
                'title' => 'Buyers (ক্রেতা)',
                'what' => 'যে কোম্পানির জন্য পোশাক বানানো হয় (H&M, Primark …) তাদের তালিকা। Inquiry, Style, Order, Inventory — সব জায়গায় buyer এখান থেকেই আসে।',
                'steps' => [
                    '<strong>+ Add</strong> চেপে Code, Name, Country, Merchandiser, Payment / Delivery Term দিন।',
                    'Save করলে buyer <strong>Approvals</strong> পাতায় যায়। Approve না হওয়া পর্যন্ত কোনো dropdown-এ আসবে না।',
                    'Reject হলে edit করে আবার save করুন — আবার approval-এ যাবে।',
                ],
                'example' => 'D-HM = H&M (Sweden) approve হলে order-এ বাছা যায়; D-ZRA = Zara approval-এর অপেক্ষায় থাকলে কোনো dropdown-এ আসে না।',
                'before' => '—',
                'after' => 'Inquiry / Style / Order',
            ],
            'masters.seasons' => [
                'title' => 'Seasons',
                'what' => 'Buyer কোন মৌসুমের জন্য অর্ডার দিচ্ছে (Spring/Summer, Autumn/Winter)। Inquiry, Style, Order-এ season বেছে নেওয়া হয় — report-এ season অনুযায়ী দেখা যায়।',
                'steps' => ['Code, Name, Year আর (চাইলে) Start / End Date দিন।'],
                'example' => 'D-SS27 = Spring/Summer 2027।',
                'before' => '—',
                'after' => 'Inquiry / Style / Order',
            ],
            'masters.product-types' => [
                'title' => 'Product Types',
                'what' => 'পোশাকের ধরন (Top, Bottom, Jacket …)। এখানে দেওয়া <strong>Default SMV</strong> নতুন style-এ আন্দাজি SMV হিসেবে কাজে লাগে।',
                'steps' => ['Code, Name, Category (knit / woven) আর Default SMV (এক পিস সেলাইয়ে গড়ে কত মিনিট) দিন।'],
                'example' => 'D-BTM = Bottom, woven, Default SMV 22।',
                'before' => '—',
                'after' => 'Inquiry / Style',
            ],
            'masters.wash-types' => [
                'title' => 'Wash Types',
                'what' => 'Garment wash-এর ধরন (Enzyme, Stone …)। Style-এ wash type দিলে বোঝা যায় washing stage লাগবে।',
                'steps' => ['Code আর Name দিন।'],
                'example' => 'D-ENZ = Enzyme Wash — D-1001 Men\'s Chino-তে ব্যবহার হয়েছে।',
                'before' => '—',
                'after' => 'Style',
            ],
            'masters.ship-modes' => [
                'title' => 'Ship Modes',
                'what' => 'মাল কীভাবে পাঠানো হবে — Sea, Air, Courier ইত্যাদি। Order-এর প্রতিটা PO line-এ ship mode বাছা হয়।',
                'steps' => ['Code আর Name দিন। সাধারণত একবার set করলেই হয়ে যায়।'],
                'example' => 'SEA = Sea Freight, AIR = Air Freight।',
                'before' => '—',
                'after' => 'Order → PO line',
            ],
            'masters.factories' => [
                'title' => 'Factories',
                'what' => 'কোন কারখানায় অর্ডার চলবে — নিজের unit না sub-contract, আর মাসে কত pcs বানাতে পারে।',
                'steps' => ['Code, Name, Capacity / Month আর Own Factory (হ্যাঁ / না) দিন।'],
                'example' => 'D-SFL1 = SFL Unit-1, মাসে 1,20,000 pcs, নিজের।',
                'before' => '—',
                'after' => 'Inquiry / Order',
            ],
            'masters.currencies' => [
                'title' => 'Currencies',
                'what' => 'Costing আর order কোন মুদ্রায় (USD, EUR …)। <strong>Exchange Rate (to BDT)</strong> দিয়ে Post Costing-এ Inventory-র টাকার খরচকে order-এর মুদ্রায় আনা হয়।',
                'steps' => ['ISO Code, Symbol আর আজকের Exchange Rate দিন। Rate বদলালে এখানে আপডেট করুন।'],
                'example' => 'USD, $, rate 120 মানে 1 USD = 120 টাকা।',
                'before' => '—',
                'after' => 'Cost Sheet / Order / Post Costing',
            ],
            'masters.item-categories' => [
                'title' => 'Item Categories',
                'what' => 'Fabric আর trims-কে দলে ভাগ করার জন্য (Fabric, Sewing Trims, Packing …)। Cost sheet আর BOM-এ এই ভাগ অনুযায়ী সাজানো হয়।',
                'steps' => ['Code, Name আর Type (fabric / trims / accessories / packing) দিন।'],
                'example' => 'D-TRM = Sewing Trims (type trims)।',
                'before' => '—',
                'after' => 'Items',
            ],
            'masters.items' => [
                'title' => 'Items (Fabric & Trims)',
                'what' => 'Costing আর BOM-এ যে কাপড় ও trims লাগে তাদের তালিকা — unit, supplier আর আন্দাজি দাম সহ।',
                'steps' => [
                    'Type, Category, Unit (Inventory থেকে আসে), Default Supplier, Default Price দিন।',
                    'Fabric হলে Composition আর GSM দিন।',
                ],
                'example' => 'D-TWL = Cotton Twill 240 GSM, unit Yrd, দাম 2.80 — Men\'s Chino-র মূল কাপড়।',
                'before' => 'Item Categories',
                'after' => 'Cost Sheet / BOM',
            ],
            'masters.sample-types' => [
                'title' => 'Sample Types',
                'what' => 'Sample-এর ধাপগুলো (Proto, Fit, Size Set, PP …) কোন ক্রমে হয় আর buyer-এর approval লাগে কিনা।',
                'steps' => ['Code, Name, Sequence (ক্রম) আর Needs Buyer Approval দিন।'],
                'example' => 'PROTO (1) → FIT (2, buyer approval লাগে) → SIZESET (3) → PP (4, Pre-Production)।',
                'before' => '—',
                'after' => 'Samples / T&A',
            ],
            'masters.machine-types' => [
                'title' => 'Machine Types',
                'what' => 'সেলাই মেশিনের ধরন (SNLS, OL, FL …)। এগুলো <strong>Inventory-র মেশিন থেকে নিজে থেকেই</strong> আসে — শুধু helper workplace (MAN, IRON) হাতে যোগ করতে হয়।',
                'steps' => [
                    'Inventory-তে নতুন ধরনের মেশিন যোগ করলে এই পাতা খুললেই type যোগ হয়ে যায়।',
                    'যেখানে মেশিন নেই (হাতের কাজ, iron) সেটা "Helper workplace" টিক দিয়ে যোগ করুন।',
                ],
                'example' => 'SNLS = Single Needle Lock Stitch; MAN = হাতের কাজ (helper)।',
                'before' => 'Inventory → Machines',
                'after' => 'Operations Library / Bulletin',
            ],
            'masters.operations' => [
                'title' => 'Operations Library',
                'what' => 'সেলাইয়ের প্রতিটা কাজের (operation) একটা তালিকা — কোন মেশিনে হয়, কী attachment লাগে, আর স্বাভাবিকভাবে কত মিনিট লাগে (Default SMV)। <strong>Bulletin</strong> বানানোর সময় এখান থেকে operation বাছলে নাম, মেশিন, attachment, SMV নিজে থেকেই বসে যায়।',
                'steps' => [
                    'Code, Name, Machine Type, Attachment আর Default SMV দিন।',
                    'Bulletin-এ style অনুযায়ী SMV দরকার হলে বদলানো যায় — এখানকার মান শুধু শুরুর মান।',
                ],
                'example' => '"Shoulder join", মেশিন OL, SMV 0.35 → Bulletin-এ বাছলে Tar/Hr = 60 ÷ 0.35 ≈ 171।',
                'before' => 'Machine Types',
                'after' => 'Bulletin → SMV → T&A capacity',
            ],

            // ---------------------------------------------------------------- Dev (R&D)
            'inquiries' => [
                'title' => 'Inquiry (Buyer-এর জিজ্ঞাসা)',
                'what' => 'Buyer প্রথম যখন জানতে চায় "এই style কত দামে, কবে দিতে পারবে" — সেই তথ্য এখানে রাখা হয়। এখান থেকেই style আর costing শুরু হয়, আর পরে দেখা যায় কতগুলো inquiry order হলো, কতগুলো lost।',
                'steps' => [
                    'Buyer, Season, Product Type, Style Ref, Order Qty, Unit Price, Target Ship Date দিন।',
                    'Status আপডেট করুন: quoted → confirmed (order হলে) বা lost (কারণ লিখে)।',
                ],
                'example' => 'H&M, D-SS27, Men\'s Chino D-1001, 1000 pcs × $9.40, status confirmed।',
                'before' => 'Buyers',
                'after' => 'Style / Cost Sheet',
            ],
            'styles' => [
                'title' => 'Style / Tech Pack',
                'what' => 'একটা পোশাকের ডিজাইনের সব তথ্য: style no, কাপড়, wash, SMV, CM, ছবি আর tech pack file। পরের সব ধাপ (costing, sample, order, BOM, bulletin) এই style ধরে চলে।',
                'steps' => [
                    'Style No, Name, Buyer, Season, Product Type, Wash Type দিন; inquiry থেকে এলে Inquiry বেছে নিন।',
                    'SMV, Target / Confirm CM (প্রতি dozen) দিন।',
                    'Show পাতায় ছবি আর tech pack file upload করুন।',
                    'Development Status এগিয়ে নিন: in development → sample stage → approved।',
                ],
                'example' => 'D-1001 Men\'s Chino — H&M, Enzyme Wash, SMV 22, status approved।',
                'before' => 'Inquiry',
                'after' => 'Cost Sheet / Samples / Order',
            ],
            'cost-sheets' => [
                'title' => 'Cost Sheet (pre-order costing, প্রতি dozen)',
                'what' => 'Order নেওয়ার আগে হিসাব: এক dozen বানাতে কাপড়, trims, process, CM, commercial আর profit মিলিয়ে কত খরচ — buyer-এর target price-এ পোষাবে কিনা। Approve করা cost sheet-ই পরে Post Costing-এ "budget" হয়।',
                'steps' => [
                    'Buyer, Style, Currency, Order Qty, Buyer Target Price দিন।',
                    'Fabric / trims row-এ item, consumption (প্রতি dozen) আর rate দিন — total নিজে হিসাব হয়।',
                    'CM / dz, Commercial %, Other Cost, Profit % দিন। নিচে FOB / pc দেখাবে — target-এর সাথে মিলিয়ে নিন।',
                    'Buyer দাম মেনে নিলে Final Price দিয়ে <strong>Approve</strong> করুন।',
                ],
                'example' => 'D-1001: Twill 15.6 yrd/dz × $2.80, thread, zipper, label, polybag; CM $24/dz → FOB প্রায় $9.40/pc।',
                'before' => 'Inquiry / Style',
                'after' => 'Order / Post Costing',
                'actions' => ['create' => 'Consumption সবসময় <strong>প্রতি dozen</strong> (12 pcs) হিসেবে দিন।'],
            ],

            // ---------------------------------------------------------------- Samples
            'samples' => [
                'title' => 'Samples',
                'what' => 'Buyer-কে পাঠানো প্রতিটা sample (Fit, PP, Size Set …) কোন অবস্থায় আছে তা track করা: Requested → In Progress → Submitted → Approved / Rejected।',
                'steps' => [
                    'Style, Sample Type, Qty, Size, Color আর Required (Due) Date দিয়ে request করুন।',
                    'বানানো শুরু হলে status "In Progress" করুন।',
                    'পাঠালে <strong>Submit</strong> করুন (তারিখ, courier, tracking no)।',
                    'Buyer-এর মতামত এলে Approve / Reject করুন — reject হলে নিজে থেকেই পরের revision তৈরি হয়।',
                ],
                'example' => 'D-1002 Kids Hoodie-র FIT sample reject হয়েছে → revision 1 খুলেছে। D-1003-র PP submitted, approval-এর অপেক্ষায়।',
                'before' => 'Style',
                'after' => 'Order / T&A (sample তারিখ নিজে বসে)',
            ],

            // ---------------------------------------------------------------- Order + BOM
            'orders' => [
                'title' => 'Orders (+ PO lines)',
                'what' => 'Buyer-এর পাকা অর্ডার। একটা order-এর ভেতরে এক বা একাধিক <strong>PO line</strong> থাকে (PO × style × color, size অনুযায়ী qty, দাম, PCD, shipment date)। Production, T&A, BOM, report — সব PO line ধরে চলে।',
                'steps' => [
                    'Order header দিন: Buyer, Season, Factory, Currency, Buyer Order Ref, Payment / Delivery Term।',
                    'Show পাতায় <strong>+ PO line</strong> দিয়ে PO No, Style, Color, size-wise qty, Unit Price, PCD আর Shipment Date দিন।',
                    'সব ঠিক হলে Status <strong>Confirmed</strong> করুন — শুধু confirmed order production-এ যায়।',
                    'Confirm-এর পর qty / PCD / shipment বদলালে revision হিসেবে লেখা থাকে (T&A Sheet-এ original / revised দেখায়)।',
                ],
                'example' => 'H&M order-এ PO D-HM-4501 (সব তৈরি, Finish Store-এ) আর D-HM-4502 (confirm-এর পর 400 → 450 করা, এখন sewing-এ)।',
                'before' => 'Cost Sheet / Samples',
                'after' => 'BOM / Bulletin / T&A / Production',
            ],
            'boms' => [
                'title' => 'BOM (Bill of Materials)',
                'what' => 'একটা style বা order বানাতে কোন কাপড়, trims, packing কত পরিমাণ লাগবে তার তালিকা। Order BOM হলে order qty দিয়ে মোট দরকারি পরিমাণ (wastage সহ) নিজে হিসাব হয়।',
                'steps' => [
                    'BOM Type বাছুন (style / order), Style বা Order দিন।',
                    'প্রতিটা item-এর consumption আর wastage % দিন — Required Qty নিজে আসে।',
                    'ঠিক থাকলে <strong>Approve</strong> করুন; পরে বদলাতে হলে <strong>Revise</strong> করুন (নতুন version)।',
                ],
                'example' => 'D-1001 order BOM: Twill 1.3 yrd/pc × 1000 pcs + wastage → মোট কত yrd কিনতে হবে।',
                'before' => 'Order / Cost Sheet',
                'after' => 'Fabric Requisition / T&A (BOM approved তারিখ)',
            ],
            'post-costing' => [
                'title' => 'Post Cost Sheet (budget বনাম actual)',
                'what' => 'Production শেষে (বা চলাকালে) একটা PO-তে আসলে কত খরচ হলো তা approved cost sheet-এর budget-এর সাথে মিলিয়ে দেখা — লাভ হলো না ক্ষতি।',
                'steps' => [
                    'এখানে entry দিতে হয় না। PO খুললেই budget (cost sheet × qty) আর actual (Inventory থেকে issue হওয়া মাল) পাশাপাশি দেখায়।',
                    'Store-এ দাম না থাকলে actual আন্দাজে (budget / pc × packed) দেখায় — সেটা চিহ্ন দিয়ে বোঝানো থাকে।',
                    'Reject loss = reject pcs × budget material / pc।',
                ],
                'example' => 'Budget material $6.50/pc × 1000 = $6,500, store থেকে issue $6,800 → material-এ $300 বেশি খরচ।',
                'before' => 'Cost Sheet (approved) / Inventory issue / Production',
                'after' => '—',
            ],

            // ---------------------------------------------------------------- Planning
            'lines' => [
                'title' => 'Lines (sewing line)',
                'what' => 'HR-এর floor-line-এর সাথে planning-এর সংখ্যা যোগ করা: কতজন operator, helper, দিনে কত মিনিট কাজ, efficiency কত %। এখান থেকেই হিসাব হয় একটা line দিনে কত pcs বানাতে পারবে।',
                'steps' => [
                    'HR floor-line বেছে Operators, Helpers, Working Minutes / Day (OT সহ) আর Efficiency % দিন।',
                    'মেশিন সংখ্যা Inventory-র মেশিন থেকে আসে (যে মেশিনের line = এই line-এর নাম)।',
                ],
                'example' => 'ধরুন Line 2: 30 operator × 600 মিনিট × 60% ÷ SMV 22 ≈ দিনে 490 pcs।',
                'before' => 'HR floor-lines / Inventory machines',
                'after' => 'Bulletin / T&A',
            ],
            'tna-templates' => [
                'title' => 'T&A Templates',
                'what' => 'T&A (Time & Action) plan-এর ছাঁচ: কোন কোন কাজ (sample, fabric in-house, PCD, sewing, shipment …) কোন ক্রমে, shipment-এর কত দিন আগে করতে হবে। নতুন T&A বানালে এই template থেকে task আর তারিখ তৈরি হয়।',
                'steps' => [
                    'Template Name আর lead days দিন (Ex-Factory, cutting, finishing + packing)।',
                    'প্রতিটা task-এ কত working day আগে, আর "auto source" দিন — যেমন bom_approved দিলে BOM approve হলেই actual তারিখ নিজে বসে।',
                    'একটা template-কে Default করুন।',
                ],
                'example' => 'Default template (MsflDefaultMasterSeeder) — 34টা task, যেমন FILE_HANDOVER, PILOT_STITCHING, FABRIC_LC।',
                'before' => 'Sample Types',
                'after' => 'T&A Plan',
            ],
            'bulletins' => [
                'title' => 'Bulletin (Operation Breakdown)',
                'what' => 'একটা style সেলাই করতে কোন কোন operation লাগে, প্রতিটা কোন মেশিনে, কত SMV — তার পূর্ণ তালিকা। এখান থেকে মোট SMV, ঘণ্টায় target, কত workplace / manpower লাগবে আর machine summary বের হয়। Approved bulletin-এর SMV দিয়েই T&A-তে line capacity হিসাব হয়।',
                'steps' => [
                    'Style, Target / Hr, Working Hours / Day দিন (চাইলে অন্য bulletin থেকে copy করুন)।',
                    'প্রতিটা row-এ <strong>Operations Library</strong> থেকে operation বাছুন — মেশিন, attachment, SMV নিজে বসে; দরকারে SMV বদলান।',
                    'নিচে Total SMV, Req W-Place, Manpower, Utilization দেখে নিন। Print করে floor-এ দিন।',
                    '<strong>Approve</strong> করুন — নতুন T&A এই SMV ব্যবহার করবে।',
                ],
                'example' => 'ধরুন Men\'s Chino-র সব operation-এর SMV যোগ = 22, target 100 pcs/hr → দরকারি workplace ≈ 22 × 100 ÷ 60 ≈ 37।',
                'before' => 'Operations Library / Style / Lines',
                'after' => 'T&A (capacity, sewing days)',
            ],
            'tna' => [
                'title' => 'T&A Plan (Time & Action)',
                'what' => 'একটা confirmed order-এর জন্য কাজের সময়সূচি: shipment date থেকে পিছনে working day গুনে প্রতিটা task-এর plan তারিখ, আর line capacity দিয়ে কত দিন sewing লাগবে। Actual তারিখ অনেকগুলো নিজে থেকেই বসে (order confirm, BOM approve, sample, cutting শুরু …)।',
                'steps' => [
                    'Confirmed Order → Style → Line(s) → T&A Template বেছে নিন।',
                    'System হিসাব করে: daily capacity = Σ line (operator × মিনিট × efficiency ÷ SMV), sewing days = qty ÷ capacity।',
                    'PCD আজকের আগে চলে গেলে plan "not feasible" দেখায় — line বাড়ান বা shipment দেখুন।',
                    'Show পাতায় যে task হাতে করতে হয় তার actual তারিখ দিন।',
                ],
                'example' => 'ধরুন 4,500 pcs, দুটো line মিলিয়ে দিনে 900 pcs → 5 দিন sewing; shipment থেকে পিছনে working day গুনে Ex-Factory, Sewing End, PCD বসে।',
                'before' => 'Order / Bulletin / Lines / T&A Template',
                'after' => 'T&A Sheet / Production',
                'actions' => ['show' => 'সবুজ = সময়মতো হয়েছে, লাল = overdue। "auto" চিহ্নের task-এর তারিখ অন্য পাতার কাজ থেকে নিজে আসে।'],
            ],
            'tna-sheet' => [
                'title' => 'T&A Sheet (buyer format)',
                'what' => 'Buyer যে 81-column T&A sheet চায়, প্রতি PO-তে এক row। বেশিরভাগ ঘর আগের ধাপগুলো (order, style, cost sheet, BOM, sample, Buyer Store GRN, T&A task) থেকে নিজে ভরে যায়।',
                'steps' => [
                    'Buyer / তারিখ দিয়ে filter করে Print করুন।',
                    'কোনো ঘর খালি থাকলে সেটা যে পাতা থেকে আসে সেখানে entry দিন (যেমন T&A task-এর actual তারিখ)।',
                    'PCD check: PASS / FAIL / PENDING — cutting সময়মতো শুরু হয়েছে কিনা।',
                ],
                'example' => 'Confirm-এর পর PO qty 400 থেকে 450 করলে sheet-এ original 400, revised 450 দেখাবে।',
                'before' => 'T&A Plan ও আগের সব ধাপ',
                'after' => 'Buyer-কে পাঠানো',
            ],

            // ---------------------------------------------------------------- Production
            'production.status' => [
                'title' => 'Production Status (PO অনুযায়ী)',
                'what' => 'প্রতিটা PO কোন stage-এ কত pcs — কত কাটা হলো, সেলাই, wash, finishing, QC, packing; প্রতিটা stage-এ WIP, rework, reject।',
                'steps' => ['এখানে entry দিতে হয় না। PO খুললে stage-wise আর size-wise হিসাব দেখায়।'],
                'example' => 'ধরুন একটা PO-তে Cutting 1000, Sewing pass 950, sewing WIP 40, reject 10 — এখানে stage-এর পাশাপাশি দেখাবে।',
                'before' => 'Cutting ও stage entry',
                'after' => '—',
            ],
            'production.requisitions' => [
                'title' => 'Fabric Requisition',
                'what' => 'Cutting-এর জন্য store থেকে কাপড় / trims চাওয়া। এটা <strong>Inventory-তে requisition</strong> হিসেবে যায়; approve হলে store মাল issue করে। Issue PO-র সাথে যুক্ত থাকে, তাই Post Costing-এ actual খরচ আসে।',
                'steps' => [
                    'Store, Requesting Department, Buyer, Style আর Order Ref দিন।',
                    'Item, qty দিন — পাশে store-এ কত stock আছে দেখায়।',
                    'Submit করলে approval-এ যায়, তারপর Inventory-তে store issue করে।',
                ],
                'example' => 'Next-এর জন্য Denim 12 oz 1,100 yrd চাওয়া হলো → store issue করার পর cutting শুরু করা যাবে।',
                'before' => 'BOM / Buyer Store GRN (মাল ঢোকা)',
                'after' => 'Cutting',
                'actions' => ['show' => 'উপরে requisition-এর তথ্য, মাঝে item-wise চাওয়া / approve / নেওয়া / বাকি, নিচে store কোন তারিখে কোন challan-এ কত দিয়েছে।', 'index' => 'Requisition No-তে click করলে details দেখবেন। Buyer / style অনুযায়ী দেখতে উপরের <strong>Buyer / Style Details</strong> আর মোট হিসাবের জন্য <strong>Summary</strong> বাটন।', 'create' => 'Buyer Store থেকে issue হয় শুধু তখনই যখন একই buyer + style-এর <strong>posted GRN</strong> আছে। "only has 0 left in the Buyer Store" error মানে ওই style-এর জন্য এখনো মাল receive (GRN) হয়নি — আগে Inventory-তে GRN করুন।'],
            ],
            'production.cuttings' => [
                'title' => 'Cutting',
                'what' => 'কাপড় কেটে কত pcs হলো — size অনুযায়ী, কোন part, আর কত pcs করে bundle। Cutting হলো production-এর প্রথম stage — যা কাটা হয় সবই "pass" ধরা হয়, পরের stage এখান থেকে নেয়।',
                'steps' => [
                    'PO, Cutting Date, Table No, Lay / Ply Count আর Fabric Used দিন।',
                    'Size অনুযায়ী pcs আর Pcs per Bundle দিন — bundle নিজে তৈরি হয় (print করা যায়)।',
                    'পরের stage এই pcs নিয়ে ফেললে cutting আর delete করা যায় না।',
                    'কাটা panel-এ reject বা rework পাওয়া গেলে <strong>Production → Reject &amp; Rework</strong>-এ Step = Cutting বেছে দিন।',
                ],
                'example' => 'ধরুন 450 pcs কাটা হলো, 25 pcs করে bundle → 18টা bundle।',
                'before' => 'Fabric Requisition (issue)',
                'after' => 'Embroidery (থাকলে) / Sewing',
            ],
            'production.entries.embroidery' => $stage('Embroidery', 'কাটা panel-এ embroidery — শুধু সেই PO-তে যেখানে "needs embroidery" টিক দেওয়া।', 'Cutting', 'Sewing', 'Kids Hoodie (front embroidery): কাটা 800 pcs থেকে আজ 300 input, 295 pass, 5 rework।'),
            'production.entries.sewing' => $stage('Sewing', 'Line-এ সেলাই — দিনে কোন line কত pcs নিল, কত pass, rework, reject। Reject কোন part-এ আর কোন মেশিনে হয়েছে তাও দেওয়া যায় (মেশিন optional)।', 'Cutting / Embroidery', 'Washing (থাকলে) / Finishing', 'ধরুন Line 2-এ আজ 200 input, 180 pass, 12 rework (open seam), 3 reject (needle hole — front part, একটা SNLS মেশিনে)।'),
            'production.entries.washing' => $stage('Washing', 'Garment wash — শুধু সেই PO-তে যেখানে "needs washing" টিক দেওয়া।', 'Sewing', 'Finishing', 'Ladies Denim Jacket (Stone Wash): 480 input, 478 pass, 2 reject (shade)।'),
            'production.entries.finishing' => $stage('Finishing', 'Thread cutting, ironing, button, tag লাগানো ইত্যাদি।', 'Sewing / Washing', 'Final QC', 'ধরুন 950 pcs input, 950 pass।'),
            'production.entries.final_qc' => $stage('Final QC', 'Packing-এর আগে শেষ quality check।', 'Finishing', 'Packing', 'ধরুন 950 input, 945 pass, 5 reject (stain)।'),
            'production.entries.packing' => $stage('Packing', 'Polybag, carton-এ ভরা। Packed qty-ই Inventory-র <strong>Finish Store</strong>-এ receive করা যায় (তার বেশি না)।', 'Final QC', 'Inventory → Finish Store receive', '945 pcs packed → Inventory-তে সর্বোচ্চ 945 pcs Finish Store-এ receive করা যাবে।'),

            'production.reject-rework' => [
                'title' => 'Reject & Rework',
                'what' => 'যেকোনো step-এ (Cutting, Embroidery, Sewing, Washing, Finishing, Final QC, Packing) reject বা rework দেওয়ার <strong>একটাই পাতা</strong> — শুধু Step বেছে নিন। দুই রকম entry: <strong>Reject / Rework found</strong> (pass হওয়া pcs-এ খারাপ পাওয়া গেল) আর <strong>Rework fixed</strong> (rework-এর pcs ঠিক হলো)।',
                'steps' => [
                    '<strong>Step</strong>, <strong>PO</strong>, তারিখ দিন (Sewing হলে Line-ও)।',
                    '<strong>Reject / Rework found:</strong> Reject আর Rework সংখ্যা দিন — এগুলো ওই step-এর pass থেকে কমে যায়। Reject flow থেকে বেরিয়ে যায়, rework ওই step-এ আটকে থাকে।',
                    '<strong>Rework fixed:</strong> rework-এর pcs ঠিক হলে "Passed after rework" দিন (পরের step নিতে পারবে); ঠিক না হলে Reject দিন।',
                    'নিচের Detail-এ প্রতিটা reject / rework-এর Part, Defect আর (চাইলে) Machine দিন — যোগফল মিলতে হবে। Machine বাধ্যতামূলক না, দিলে Defect Analysis-এ machine-wise rejection দেখা যায়।',
                ],
                'example' => 'Cutting-এ Black PO-র কাটা 810 pcs-এর মধ্যে 3টা Front panel-এ fabric hole (Reject 3) আর 5টা Sleeve বাঁকা কাটা (Rework 5) → Cutting pass 802। পরে 5টা recut হলে "Rework fixed": Passed 5 → Cutting pass 807।',
                'before' => 'যেকোনো step-এর entry',
                'after' => 'Production Status / Defect Analysis / Post Costing',
                'actions' => ['create' => 'উপরের বক্সে দেখাবে কত pcs-এর মধ্যে থেকে reject / rework দেওয়া যাবে — পরের step যা নিয়ে ফেলেছে তা আর এখানে ধরা যায় না।'],
            ],

            // ---------------------------------------------------------------- Reports
            'reports' => [
                'title' => 'Reports',
                'what' => 'সব entry থেকে তৈরি report — screen-এ দেখা আর print করা যায়। এখানে কিছু entry দিতে হয় না।',
                'steps' => ['Report বাছুন → buyer / তারিখ / stage / line দিয়ে filter → Print।'],
                'example' => 'Daily Production দিয়ে আজ কোন line কত pass দিল দেখুন।',
                'before' => 'সব পাতার entry',
                'after' => '—',
            ],
            'reports.order-book' => self::report('Order Book', 'চলমান order-এর প্রতিটা PO line: qty, value, তারিখ, কত packed আর কত Finish Store-এ।', 'Order / Packing / Finish Store'),
            'reports.daily-production' => self::report('Daily Production', 'দিন অনুযায়ী প্রতিটা stage-এর input, pass, rework, reject — line অনুযায়ী filter করা যায়।', 'Cutting ও stage entry'),
            'reports.defects' => self::report('Defect Analysis', 'Reject আর rework কোন stage-এ, কোন part-এ, কোন মেশিনে, কোন defect-এ বেশি হচ্ছে।', 'Stage entry-র defect row'),
            'reports.shipment-status' => self::report('Shipment Status', 'Shipment তারিখ অনুযায়ী PO, কত কাটা / সেলাই / packed, আর সময়মতো হবে কিনা (risk)।', 'Order / Production'),
            'reports.tna-status' => self::report('T&A Status', 'চলমান T&A-র যে task এখনো খোলা — overdue আর সামনের 7 দিনে due।', 'T&A Plan'),
            'reports.requisition-details' => self::report('Requisition Details', 'Buyer / style / PO অনুযায়ী প্রতিটা requisition-এর item: কত চাওয়া হলো, কত approve, কত store থেকে নেওয়া হলো, আর কবে কত নেওয়া হলো (তারিখ: qty, challan)। Style দিয়ে filter করলে এক style-এর সব requisition একসাথে দেখা যায়।', 'Fabric Requisition / Inventory issue'),
            'reports.requisition-summary' => self::report('Requisition Summary', 'Buyer → style → item অনুযায়ী মোট: কয়টা requisition, কত চাওয়া, approve, নেওয়া, কত এখনো বাকি, আর প্রথম ও শেষ কবে নেওয়া হলো।', 'Fabric Requisition / Inventory issue'),
            'reports.sample-turnaround' => self::report('Sample Turnaround', 'প্রতিটা sample: request → submit → decision, কত দিন লাগল।', 'Samples'),
        ];
    }

    private static function report(string $title, string $what, string $source): array
    {
        return [
            'title' => $title . ' Report',
            'what' => $what,
            'steps' => ['উপরের filter দিয়ে buyer / তারিখ বেছে নিন, তারপর <strong>Print</strong>।', 'এখানে entry দিতে হয় না — সংখ্যা আসে: ' . $source . '।'],
            'example' => null,
            'before' => $source,
            'after' => '—',
        ];
    }
}
