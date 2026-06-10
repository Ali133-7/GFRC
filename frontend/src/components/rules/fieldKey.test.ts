import { describe, it, expect } from "vitest";
import { fieldKey, findFieldByKey, isChoiceField, getFieldOptions } from "./fieldKey";

const registerBacked: any = {
  id: "08db01a7-1117-4000-0000-000000000000", // WorkflowField PK (must NOT be the key)
  register_field_id: "30555836-9fcc-43f4-b97b-0ae9daca3ef4", // the execution key
  field_type: "select",
  registerField: { id: "30555836", field_type: "select", label_ar: "الفئة", options: [{ label_ar: "الممتاز", value: "الممتاز" }, { label_ar: "العادي", value: "العادي" }] },
};

const customField: any = {
  id: "75f26f79-6d41-43a2-a2cd-3085d84aa0b1",
  register_field_id: null,
  field_type: "text",
};

describe("fieldKey", () => {
  it("uses register_field_id for register-backed fields, NOT the WorkflowField PK", () => {
    expect(fieldKey(registerBacked)).toBe("30555836-9fcc-43f4-b97b-0ae9daca3ef4");
    expect(fieldKey(registerBacked)).not.toBe(registerBacked.id);
  });

  it("uses custom_<id> for custom fields", () => {
    expect(fieldKey(customField)).toBe("custom_75f26f79-6d41-43a2-a2cd-3085d84aa0b1");
  });

  it("findFieldByKey resolves a field by its execution key, matching the engine's value keys", () => {
    const fields = [registerBacked, customField];
    expect(findFieldByKey(fields, "30555836-9fcc-43f4-b97b-0ae9daca3ef4")).toBe(registerBacked);
    expect(findFieldByKey(fields, "custom_75f26f79-6d41-43a2-a2cd-3085d84aa0b1")).toBe(customField);
    // The old (buggy) key — the WorkflowField PK — must NOT resolve.
    expect(findFieldByKey(fields, registerBacked.id)).toBeUndefined();
  });

  it("detects choice fields and reads inherited options", () => {
    expect(isChoiceField(registerBacked)).toBe(true);
    expect(isChoiceField(customField)).toBe(false);
    expect(getFieldOptions(registerBacked).map((o) => o.value)).toEqual(["الممتاز", "العادي"]);
  });
});
