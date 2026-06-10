import { Button } from "@/components/ui/Button";
import { LoadingSpinner } from "@/components/ui/LoadingSpinner";

interface WorkflowWizardFooterProps {
  stepIndex: number;
  totalSteps: number;
  isFetching: boolean;
  isSubmitting: boolean;
  isCompleting: boolean;
  onBack?: () => void;
  onNext: () => void;
  onComplete?: () => void;
  canComplete?: boolean;
}

export function WorkflowWizardFooter({
  stepIndex,
  totalSteps,
  isFetching,
  isSubmitting,
  isCompleting,
  onBack,
  onNext,
  onComplete,
  canComplete = false,
}: WorkflowWizardFooterProps) {
  const isLastStep = stepIndex >= totalSteps - 1;
  const isBusy = isFetching || isSubmitting || isCompleting;

  return (
    <div className="flex items-center justify-between border-t border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
      <div>
        {stepIndex > 0 && (
          <Button variant="outline" onClick={onBack} disabled={isBusy}>
            السابق
          </Button>
        )}
      </div>

      <div className="flex items-center gap-3">
        {isLastStep && canComplete ? (
          <Button
            onClick={onComplete}
            disabled={isBusy}
            className={isBusy ? "opacity-50 cursor-not-allowed" : ""}
          >
            {isCompleting ? (
              <>
                <LoadingSpinner />
                <span className="mr-2">جارٍ الإكمال...</span>
              </>
            ) : isFetching ? (
              <>
                <LoadingSpinner />
                <span className="mr-2">جارٍ حساب الرسوم...</span>
              </>
            ) : (
              "تأكيد وإنشاء الوصل"
            )}
          </Button>
        ) : (
          <Button
            onClick={onNext}
            disabled={isBusy}
            className={isBusy ? "opacity-50 cursor-not-allowed" : ""}
          >
            {isSubmitting ? (
              <>
                <LoadingSpinner />
                <span className="mr-2">جارٍ الحفظ...</span>
              </>
            ) : (
              "التالي"
            )}
          </Button>
        )}
      </div>
    </div>
  );
}
