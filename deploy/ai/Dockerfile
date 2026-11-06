FROM python:3.11-slim
WORKDIR /app
COPY ai_engine/requirements.txt ./requirements.txt
RUN pip install --no-cache-dir -r requirements.txt
COPY ai_engine/ ./
RUN useradd --no-create-home --system appuser && chown -R appuser:appuser /app
USER appuser
EXPOSE 5001
CMD ["gunicorn", "-w", "2", "-b", "0.0.0.0:5001", "--timeout", "90", "app:app"]
