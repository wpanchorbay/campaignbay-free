import { check, edit, Icon } from "@wordpress/icons";
import React, { useState, useRef, useEffect } from "react";

interface EditableTextProps {
  value: string | null | undefined;
  onChange: (value: string) => void;
  placeholder?: string;
  className?: string;
  disabled?: boolean;
  classNames?: {
    root?: string;
    text?: string;
    input?: string;
    iconButton?: string;
    icon?: string;
  };
  error?: string | undefined;
}

export const EditableText: React.FC<EditableTextProps> = ({
  value,
  onChange,
  placeholder = "Click to edit...",
  className = "",
  disabled = false,
  classNames,
  error 
}) => {
  const [isEditing, setIsEditing] = useState(false);
  const [localValue, setLocalValue] = useState(value ?? "");
  const inputRef = useRef<HTMLInputElement>(null);
  const cancelled = useRef(false);

  // Sync local state when prop value changes
  useEffect(() => {
    setLocalValue(value ?? "");
  }, [value]);

  const handleSave = () => {
    if (localValue !== (value ?? "")) {
      onChange(localValue);
    }
    setIsEditing(false);
    inputRef.current?.blur();
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === "Enter") {
      handleSave();
    } else if (e.key === "Escape") {
      cancelled.current = true;
      setLocalValue(value ?? "");
      setIsEditing(false);
      inputRef.current?.blur();
    }
  };

  const onFocus = () => {
    if (!disabled) {
      setIsEditing(true);
    }
  };

  const onBlur = () => {
    // The blur raised by Escape must not save the text that was being edited.
    if (cancelled.current) {
      cancelled.current = false;
      setIsEditing(false);
      return;
    }
    // Trigger save on blur
    handleSave();
  };

  return (
    <div>
    <div
      className={`campaignbay-flex campaignbay-items-center campaignbay-gap-2 ${className} ${
        classNames?.root || ""
      }`}
    >
      <input
        ref={inputRef}
        type="text"
        value={localValue}
        onChange={(e) => setLocalValue(e.target.value)}
        onFocus={onFocus}
        onBlur={onBlur}
        onKeyDown={handleKeyDown}
        readOnly={disabled}
        placeholder={placeholder}
        className={`
          !campaignbay-bg-transparent !campaignbay-shadow-none
          campaignbay-text-[#1e1e1e] campaignbay-font-[700] campaignbay-text-[20px] campaignbay-leading-[32px]
          campaignbay-px-1 campaignbay-py-0.5
          campaignbay-w-auto 
          !campaignbay-border-t-0 !campaignbay-border-l-0 !campaignbay-border-r-0 !campaignbay-border-b-2
           !campaignbay-rounded-[0px]
          focus:campaignbay-outline-none
          campaignbay-transition-colors campaignbay-duration-200 placeholder:campaignbay-italic
          ${error? "!campaignbay-border-red-500": "!campaignbay-border-transparent focus:!campaignbay-border-[#3858e9]"}
          ${
            isEditing
              ? ""
              : "campaignbay-cursor-pointer"
          }
          ${
            disabled
              ? "campaignbay-cursor-not-allowed campaignbay-opacity-60"
              : ""
          }
          ${isEditing ? classNames?.input || "" : classNames?.text || ""}
        `}
      />

      {!disabled && (
        <button
          type="button"
          // Prevent blur on mousedown so click event fires properly
          onMouseDown={(e) => e.preventDefault()}
          onClick={(e) => {
            e.stopPropagation();
            if (isEditing) {
              handleSave();
            } else {
              inputRef.current?.focus();
            }
          }}
          className={`
            campaignbay-p-1 campaignbay-rounded-full campaignbay-transition-colors
            ${
              isEditing
                ? "campaignbay-text-primary hover:campaignbay-bg-blue-50"
                : "campaignbay-text-gray-400 hover:campaignbay-text-primary hover:campaignbay-bg-gray-100"
            }
            ${classNames?.iconButton || ""}
          `}
          aria-label={isEditing ? "Save" : "Edit"}
        >
          {isEditing ? (
           <Icon icon={check} fill="currentColor" />
          ) : (
            <Icon icon={edit} fill="currentColor" />
          )}
        </button>
      )}
    </div>
    {
        error && (
            <span className="campaignbay-text-red-500 campaignbay-text-sm campaignbay-mt-1">
                {error}
            </span>
        )
    }
    </div>
  );
};
