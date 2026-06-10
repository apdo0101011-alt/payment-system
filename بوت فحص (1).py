import html
import random
import re
import requests
import telebot
from telebot.types import InlineKeyboardButton, InlineKeyboardMarkup

# ----------------- الإعدادات الخاصة بك -----------------
TOKEN = "8612953985:AAFDzKLd9elmwOZmOmUYEjphOmvGY-WRpzE"
GATEWAY_URL = "http://localhost:8080/process_payment.php"
API_KEY = "pk_live_default_node"
# -----------------------------------------------------

bot = telebot.TeleBot(TOKEN)

def get_bin_info(card_number):
    bin_num = str(re.sub(r"\D", "", str(card_number)))[:6]
    info = {"brand": "VISA", "type": "CREDIT", "bank": "SECURE LIVE NODE", "country": "UNKNOWN"}
    if not bin_num or len(bin_num) < 6: return info, bin_num
    try:
        url = f"http://localhost:8080/bins/bin.php?bin={bin_num}"
        data = requests.get(url, timeout=3).json()
        if data.get("status") in ["success", "not_found"]:
            info["brand"] = str(data.get("brand", "VISA")).upper()
            info["type"] = str(data.get("type", "CREDIT")).upper()
            info["bank"] = str(data.get("bank", "SECURE LIVE NODE")).upper()
            info["country"] = str(data.get("country", "UNKNOWN")).upper()
    except: pass
    if bin_num.startswith("4"): info["brand"] = "VISA"
    elif bin_num.startswith(("51", "52", "53", "54", "55", "50")): info["brand"] = "MASTERCARD"
    return info, bin_num

def generate_cards(bin_number, count=20):
    cards = []
    bin_clean = re.sub(r"\D", "", str(bin_number))
    if not bin_clean or len(bin_clean) < 6: return None
    for _ in range(count):
        cc_number = bin_clean + "".join([str(random.randint(0, 9)) for _ in range(16 - len(bin_clean))])
        cards.append(f"{cc_number}|{random.randint(1,12):02d}|{random.randint(2026,2032)}|{random.randint(100,999):03d}")
    return cards

def check_card_api(raw_input_string):
    try:
        cleaned_input = re.sub(r"[\[\]']", "", str(raw_input_string).strip())
        parts = [p.strip() for p in cleaned_input.split("|") if p.strip()]
        if len(parts) < 4: return f"❌ <code>{html.escape(cleaned_input)}</code> -> صيغة خاطئة"
        
        cc, mes, ano, cvc = parts[0], parts[1], parts[2], parts[3]
        bin_info, bin_num = get_bin_info(cc)
        
        payload = {"api_key": API_KEY, "amount": "10", "card_number": cc, "expiry": f"{mes}/{ano}", "cvv": cvc}
        res_raw = requests.post(GATEWAY_URL, data=payload, timeout=12).text.strip()
        
        is_live = False
        reason_message = res_raw
        if "payment complete successfully" in res_raw.lower() or "successfully on live mode" in res_raw.lower(): is_live = True
        else:
            try:
                res_json = json.loads(res_raw)
                if res_json.get("bank"): bin_info["bank"] = str(res_json.get("bank")).upper()
                if res_json.get("country"): bin_info["country"] = str(res_json.get("country")).upper()
                if str(res_json.get("status")).lower() in ["success", "approved", "live", "true"]: is_live = True
                else: reason_message = res_json.get("message", res_json.get("error", res_raw))
            except:
                if any(x in res_raw.lower() for x in ["success", "approved", "live"]): is_live = True

        reason_clean = html.escape(str(reason_message))
        card_clean = html.escape(f"{cc}|{mes}|{ano}|{cvc}")

        if is_live:
            return f"🔥 <b>APPROVED [LIVE]</b> 🔥\n=========================\n💳 <b>Card:</b> <code>{card_clean}</code>\nℹ️ <b>Status:</b> Approved ✅\n🚀 <b>Gateway:</b> Ultra Strict Gateway v2\n🌐 <b>Brand:</b> {bin_info['brand']} ({bin_info['type']})\n🏦 <b>Bank:</b> {bin_info['bank']}\n🌍 <b>Country:</b> {bin_info['country']}\n=========================\n🔑 <b>Auth Code:</b> <code>AUTH_{random.randint(100000, 999999)}</code>\n🆔 <b>Charge ID:</b> <code>ch_live_{random.randint(10000000, 99999999)}cdad2c</code>\n=========================\n💎 <b>تم صيد الكارت وتثبيته في مستويات الصلاحية!</b>"
        else:
            return f"❌ <b>DECLINED [DEAD]</b>\n=========================\n💳 <b>Card:</b> <code>{card_clean}</code>\nℹ️ <b>Reason:</b> {reason_clean}\n🚀 <b>Gateway:</b> Ultra Strict Gateway v2\n🌐 <b>Brand:</b> {bin_info['brand']} ({bin_info['type']})\n🏦 <b>Bank:</b> {bin_info['bank']}\n🌍 <b>Country:</b> {bin_info['country']}\n🔢 <b>BIN:</b> {bin_num}\n-------------------------"
    except Exception as e: return f"⚠️ <b>ERROR</b> -> فشل في الفحص: {html.escape(str(e))}"

@bot.message_handler(commands=["start"])
def start_command(message):
    welcome_text = f"🙋‍♂️ أهلاً بك في بوت فحص بطاقات الائتمان!\n\n🔹 <code>/cc رقم_الكارت|شهر|سنة|cvv</code>\n🔹 <code>/mass [قائمة كروت]</code>\n🔹 <code>/gen BIN</code>"
    markup = InlineKeyboardMarkup()
    markup.row(InlineKeyboardButton("💳 طريقة الفحص", callback_data="help_cc"), InlineKeyboardButton("🎲 طريقة التوليد", callback_data="help_gen"))
    markup.row(InlineKeyboardButton("🚀 طريقة الـ Mass", callback_data="help_mass"))
    bot.reply_to(message, welcome_text, reply_markup=markup, parse_mode="HTML")

@bot.callback_query_handler(func=lambda call: True)
def callback_inline(call):
    if call.data == "back_main":
        markup = InlineKeyboardMarkup()
        markup.row(InlineKeyboardButton("💳 طريقة الفحص", callback_data="help_cc"), InlineKeyboardButton("🎲 طريقة التوليد", callback_data="help_gen"))
        markup.row(InlineKeyboardButton("🚀 طريقة الـ Mass", callback_data="help_mass"))
        bot.edit_message_text(f"🙋‍♂️ أهلاً بك في بوت فحص بطاقات الائتمان!\n\n🔹 <code>/cc رقم_الكارت|شهر|سنة|cvv</code>\n🔹 <code>/mass [قائمة كروت]</code>\n🔹 <code>/gen BIN</code>", call.message.chat.id, call.message.message_id, reply_markup=markup, parse_mode="HTML")
        return
    
    res_txt = ""
    if call.data == "help_cc": res_txt = "💡 <b>الفحص الفردي:</b>\n\n<code>/cc 5547301779196774|01|2028|301</code>"
    elif call.data == "help_gen": res_txt = "💡 <b>توليد الكروت:</b>\n\n<code>/gen 554730</code>"
    elif call.data == "help_mass": res_txt = "💡 <b>الفحص الجماعي Mass:</b>\n\n<code>/mass\nكارت1\nكارت2</code>"
    
    markup = InlineKeyboardMarkup()
    markup.row(InlineKeyboardButton("⬅️ العودة للرئيسية", callback_data="back_main"))
    bot.edit_message_text(res_txt, call.message.chat.id, call.message.message_id, reply_markup=markup, parse_mode="HTML")

@bot.message_handler(commands=["cc"])
def cc_command(message):
    card = message.text.replace("/cc", "").strip()
    if not card: bot.reply_to(message, "❌ الاستخدام الصحيح: /cc رقم_الكارت|شهر|سنة|cvv"); return
    status_msg = bot.reply_to(message, "⏳ جاري فحص الكارت واستخراج البيانات الحية...")
    bot.edit_message_text(check_card_api(card), message.chat.id, status_msg.message_id, parse_mode="HTML")

@bot.message_handler(commands=["mass"])
def mass_command(message):
    cards = re.findall(r"\d{15,16}\|\d{2}\|\d{2,4}\|\d{3,4}", message.text.replace("/mass", ""))
    if not cards: bot.reply_to(message, "❌ لم يتم العثور على كروت صالحة."); return
    bot.reply_to(message, f"⏳ جاري فحص {len(cards[:20])} كارت، انتظر...")
    for card in cards[:20]: bot.send_message(message.chat.id, check_card_api(card), parse_mode="HTML")

@bot.message_handler(commands=["gen"])
def gen_command(message):
    bin_in = message.text.replace("/gen", "").strip()
    if not bin_in: bot.reply_to(message, "❌ الاستخدام الصحيح: /gen 410234"); return
    g_cards = generate_cards(bin_in)
    if not g_cards: bot.reply_to(message, "❌ الـ BIN غير صالح."); return
    bot.reply_to(message, f"🎲 <b>تم توليد 20 كارت بنجاح:</b>\n\n<code>{html.escape(chr(10).join(g_cards))}</code>\n\n💡 افحصها بـ /mass", parse_mode="HTML")

print("\n[+] البوت مستقر وجاهز للتشغيل الفوري بنسبة 100%...")
bot.infinity_polling()
