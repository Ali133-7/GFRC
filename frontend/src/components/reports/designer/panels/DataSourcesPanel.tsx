import React, { useCallback, useEffect, useState } from "react";
import { BusinessFieldBrowser, fieldToReportField } from "./BusinessFieldBrowser";
import { useBusinessRegisters, useBusinessFields } from "@/hooks/useBusinessReportData";
import {
  getFavoriteFieldIds,
  getRecentFieldIds,
  toggleFieldFavorite,
  recordFieldUsage,
} from "@/utils/fieldBrowserStorage";
import type { ReportField } from "@/types/report";
import "./BusinessFieldBrowser.css";

interface DataSourcesPanelProps {
  selectedRegisterIds: string[];
  onSelectedRegistersChange: React.Dispatch<React.SetStateAction<string[]>>;
  onDropField: (field: ReportField, sectionId: string) => void;
}

export function DataSourcesPanel({
  selectedRegisterIds,
  onSelectedRegistersChange,
  onDropField,
}: DataSourcesPanelProps) {
  const { data: registers = [], isLoading: isLoadingRegisters } = useBusinessRegisters();
  const { data: fields = [], isLoading: isLoadingFields } = useBusinessFields(selectedRegisterIds);
  const [favoriteIds, setFavoriteIds] = useState<string[]>(() => getFavoriteFieldIds());
  const [recentIds, setRecentIds] = useState<string[]>(() => getRecentFieldIds());

  useEffect(() => {
    // If registers loaded and none selected, do not auto-select.
    // User must explicitly choose registers for the report.
  }, [registers]);

  const handleSelectRegister = useCallback(
    (registerId: string) => {
      onSelectedRegistersChange((prev: string[]) => {
        if (prev.includes(registerId)) {
          return prev.filter((id: string) => id !== registerId);
        }
        return [...prev, registerId];
      });
    },
    [onSelectedRegistersChange]
  );

  const handleToggleFavorite = useCallback((fieldId: string) => {
    const isFavorite = toggleFieldFavorite(fieldId);
    setFavoriteIds(getFavoriteFieldIds());
    return isFavorite;
  }, []);

  const handleRecordUsage = useCallback((fieldId: string) => {
    recordFieldUsage(fieldId);
    setRecentIds(getRecentFieldIds());
  }, []);

  const handleDropField = useCallback(
    (field: ReportField, sectionId: string) => {
      if (field.id) {
        recordFieldUsage(field.id);
        setRecentIds(getRecentFieldIds());
      }
      onDropField(field, sectionId);
    },
    [onDropField]
  );

  return (
    <BusinessFieldBrowser
      registers={registers}
      fields={fields}
      selectedRegisterIds={selectedRegisterIds}
      favoriteIds={favoriteIds}
      recentIds={recentIds}
      isLoadingRegisters={isLoadingRegisters}
      isLoadingFields={isLoadingFields}
      onSelectRegister={handleSelectRegister}
      onToggleFavorite={handleToggleFavorite}
      onRecordUsage={handleRecordUsage}
      onDropField={handleDropField}
    />
  );
}

export { fieldToReportField };
