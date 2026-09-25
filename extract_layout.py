import pandas as pd

def extract_sheet_layout():
    file_path = "PLANILLAS FIJOS VIEJO FORMATO  2023- copia (1).xls"
    xls = pd.ExcelFile(file_path, engine='xlrd')
    df = pd.read_excel(xls, sheet_name='3')
    
    with open("sheet_3_layout.txt", "w", encoding="utf-8") as f:
        for index, row in df.iterrows():
            f.write(f"Row {index+1}:\n")
            for col_idx, val in enumerate(row):
                if pd.notna(val):
                    f.write(f"  Col {col_idx}: {val}\n")
            f.write("-" * 20 + "\n")

if __name__ == "__main__":
    extract_sheet_layout()
