const ones = [
  "", "واحد", "اثنان", "ثلاثة", "أربعة", "خمسة",
  "ستة", "سبعة", "ثمانية", "تسعة", "عشرة",
  "أحد عشر", "اثنا عشر", "ثلاثة عشر", "أربعة عشر", "خمسة عشر",
  "ستة عشر", "سبعة عشر", "ثمانية عشر", "تسعة عشر",
];

const tens = [
  "", "", "عشرون", "ثلاثون", "أربعون", "خمسون",
  "ستون", "سبعون", "ثمانون", "تسعون",
];

const hundreds = [
  "", "مائة", "مئتان", "ثلاثمائة", "أربعمائة", "خمسمائة",
  "ستمائة", "سبعمائة", "ثمانمائة", "تسعمائة",
];

function threeDigits(n: number): string {
  if (n === 0) return "";
  const h = Math.floor(n / 100);
  const rem = n % 100;
  const t = Math.floor(rem / 10);
  const o = rem % 10;

  const parts: string[] = [];

  if (h > 0) parts.push(hundreds[h]);

  if (rem > 0) {
    if (rem < 20) {
      parts.push(ones[rem]);
    } else {
      if (o > 0) {
        parts.push(ones[o] + " و" + tens[t]);
      } else {
        parts.push(tens[t]);
      }
    }
  }

  return parts.join(" و");
}

function bigNumberWords(n: number): string {
  const parts: string[] = [];

  const billions = Math.floor(n / 1_000_000_000);
  const millions = Math.floor((n % 1_000_000_000) / 1_000_000);
  const thousands = Math.floor((n % 1_000_000) / 1_000);
  const remainder = n % 1_000;

  if (billions > 0) {
    const w = threeDigits(billions);
    if (billions === 1) parts.push("مليار");
    else if (billions === 2) parts.push("ملياران");
    else parts.push(w + " مليارات");
  }

  if (millions > 0) {
    const w = threeDigits(millions);
    if (millions === 1) parts.push("مليون");
    else if (millions === 2) parts.push("مليونان");
    else parts.push(w + " ملايين");
  }

  if (thousands > 0) {
    const w = threeDigits(thousands);
    if (thousands === 1) parts.push("ألف");
    else if (thousands === 2) parts.push("ألفان");
    else if (thousands <= 10) parts.push(w + " آلاف");
    else parts.push(w + " ألف");
  }

  if (remainder > 0) {
    parts.push(threeDigits(remainder));
  }

  return parts.join(" و");
}

export function amountToArabicWords(amount: number): string {
  if (isNaN(amount) || amount < 0) return "صفر دينار عراقي";
  if (amount === 0) return "صفر دينار عراقي";

  const intPart = Math.floor(amount);
  const decimalPart = Math.round((amount - intPart) * 1000);

  let result = "";

  if (intPart > 0) {
    result = bigNumberWords(intPart) + " دينار";
  }

  if (decimalPart > 0) {
    const filsText = threeDigits(decimalPart) + " فلس";
    if (intPart > 0) {
      result += " و" + filsText;
    } else {
      result = filsText;
    }
  }

  if (result === "") {
    return "صفر دينار عراقي";
  }

  return result + " عراقي";
}

export default amountToArabicWords;
