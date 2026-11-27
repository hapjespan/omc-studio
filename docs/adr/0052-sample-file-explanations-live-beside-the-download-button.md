# 0052: Sample file explanations live beside the download button

Date: 2026-11-27
Status: accepted

## Decision

Each CSV import (cost records, structure, allocations) offers a sample file that holds only the exact FR-084 headers and a few realistic rows without a client name. The explanation per column (content, required or not, format such as the decimal comma) is not part of the file. The import page shows it next to the download button, read from `lang/nl.json` under `exchange.sample.<group>.<column>`. `CsvReader` skips no line: every row reaches validation.

## Motivation

Explanation lines inside the file need a comment convention in the reader, and any such convention can swallow a real row that starts with the comment mark. A sample that uploads as is, with a reader that treats every line alike, removes that risk. The explanation is Dutch UI text, so it belongs in the messages file and on the page, where it stays readable without opening a spreadsheet. This reverses the earlier owner decision 2b of this line (explanation lines inside the file, skipped by the reader); the owner revised it on 2026-11-27.
