# SQL (Club61)

**Convenção:** ao adicionar colunas a tabelas já existentes no Supabase/PostgreSQL, usar sempre:

```sql
ALTER TABLE table_name
ADD COLUMN IF NOT EXISTS column_name data_type;
```

Não usar declarações de coluna isoladas sem `ALTER TABLE`. Ver `.cursor/rules/sql-alter-columns.mdc`.
